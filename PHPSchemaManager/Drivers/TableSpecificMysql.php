<?php
namespace PHPSchemaManager\Drivers;

/**
 * Description of TableSpecificMysql
 *
 * @author thiago
 */
class TableSpecificMysql extends TableSpecific
{
    const MYISAM = 'MYISAM';
    const INNODB = 'InnoDb';
    const MEMORY = 'MEMORY';
    const CSV = 'CSV';
    const BLACKHOLE = 'BLACKHOLE';

    protected $engine;

    public function markAsMyIsam()
    {
        $this->setEngine(self::MYISAM);
    }

    public function markAsInnoDb()
    {
        $this->setEngine(self::INNODB);
    }

    public function markAsMemory()
    {
        $this->setEngine(self::MEMORY);
    }

    public function markAsCsv()
    {
        $this->setEngine(self::CSV);
    }

    public function markAsBlackhole()
    {
        $this->setEngine(self::BLACKHOLE);
    }

    public function isMyIsam()
    {
        return $this->getEngine() == self::MYISAM;
    }

    public function isInnoDb()
    {
        return $this->getEngine() == self::INNODB;
    }

    public function isMemory()
    {
        return $this->getEngine() == self::MEMORY;
    }

    public function isCsv()
    {
        return $this->getEngine() == self::CSV;
    }

    public function isBlackhole()
    {
        return $this->getEngine() == self::BLACKHOLE;
    }

    protected function setEngine($engineName = self::MYISAM)
    {
        if (FALSE !== array_search($engineName, $this->allowedEngineTypes())) {
            $this->engine = $engineName;
        } else {
            throw new \PHPSchemaManager\Exceptions\MysqlException("$engineName is not a supported engine");
        }
    }

    protected function getEngine() {
        return $this->engine;
    }

    protected function allowedEngineTypes()
    {
        return array(self::MYISAM, self::INNODB, self::MEMORY, self::CSV, self::BLACKHOLE);
    }
}
