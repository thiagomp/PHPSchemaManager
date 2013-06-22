<?php

class Build {
    protected $branch;
    protected $codeCoverageOutputDir;

    public function __construct($branch = 'development')
    {
        $this->branch = empty($branch) ? 'development' : $branch;
        $this->codeCoverageOutputDir = rtrim(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . "phpsm" . DIRECTORY_SEPARATOR;
    }

    public function getBranch() {
        return $this->branch;
    }

    public function getCodeCoverageOutputDir()
    {
        return $this->codeCoverageOutputDir;
    }

    function execute() {
        $psmDir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
        $reportDir = $this->getCodeCoverageOutputDir();
        $branchDir = $reportDir . DIRECTORY_SEPARATOR . $this->getBranch();

        $this->deleteDirectory($reportDir);
        mkdir($reportDir, 0777);
        mkdir($branchDir, 0777);

        copy(realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . "codeCoverageIndex.html", $reportDir . "index.html");

        // First, execute the tests
        $cmd = "cd $psmDir && phpunit --testsuite PHPSchemaManagerSuite --coverage-html $branchDir";
        $ret = system($cmd);

        if (false === $ret) {
            die("Unit Test failed" . PHP_EOL);
        }
        echo "Finished PHPUnit..." . PHP_EOL;

        // TODO find a way to check if PSR2 coding standard is installed in the system
        $cmd = "cd $psmDir && phpcs --standard=PSR2 PHPSchemaManager";
        $ret = system($cmd);

        if (false === $ret) {
            echo "$cmd\n";
            die("Code Sniffer failed [$ret]" . PHP_EOL);
        }
        echo "Finished Code Sniffer..." . PHP_EOL;

        // Deploy the Code Coverage report at appfog

        $this->deploy('appfog');
    }

    private function deploy($where)
    {
        switch($where) {
            case 'appfog':
                $this->deployAppFog();
                break;
            case 'pagoda':
                $this->deployPagoda();
                break;
            default:
                die("I'm not prepared to deploy at $where");
        }
    }

    private function deployPagoda()
    {
        $message = "Automatic report update";
        $cmd = "cd {$this->getCodeCoverageOutputDir()} && git add . && git commit -m '$message' && git push pagoda --all";

        $ret = system($cmd);

        if (false === $ret) {
            die("error trying to push the report to pagoda" . PHP_EOL);
        }
    }

    private function deployAppFog()
    {
        // check if expected variables exists
        $afEmail = getenv('AFEMAIL');
        $afPassword = getenv('AFPASSWORD');
        $afApp = "phpsm";

        if (empty($afEmail) || empty($afPassword)) {
            echo "Will not deploy the code coverage results" . PHP_EOL;
            echo "set the AFEMAIL and AFPASSWORD system variables if you want to deploy" . PHP_EOL . PHP_EOL;
            var_dump($_ENV);
            return 0;
        }
        echo "Starting Code Coverage deploy to appfog..." . PHP_EOL;

        $cmd = "af login --email $afEmail --passwd $afPassword && cd {$this->getCodeCoverageOutputDir()} && af update $afApp";
        $ret = system($cmd);

        if (false === $ret) {
            die("error trying to push the report to appfog" . PHP_EOL);
        }
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;

        if (!is_dir($dir) || is_link($dir)) return unlink($dir);
            foreach (scandir($dir) as $item) {
                if ($item == '.' || $item == '..') continue;
                if (!$this->deleteDirectory($dir . "/" . $item)) {
                    chmod($dir . "/" . $item, 0777);
                    if (!$this->deleteDirectory($dir . "/" . $item)) return false;
                };
            }
            return rmdir($dir);
        }
}