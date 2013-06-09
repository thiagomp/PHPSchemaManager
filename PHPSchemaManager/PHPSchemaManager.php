<?php
namespace PHPSchemaManager;


/**
 * Description of SchemaManager
 *
 * @author thiago
 */
class PHPSchemaManager
{

    protected $schemas = array();
    protected $connections = array();

    public static function registerAutoload()
    {
        spl_autoload_register(__NAMESPACE__ . '\PHPSchemaManager::autoload');
    }

    /**
     * PSR-0 autoloader
     */
    public static function autoload($className)
    {
        $thisClass = str_replace(__NAMESPACE__.'\\', '', __CLASS__);

        $baseDir = __DIR__;

        if (substr($baseDir, -strlen($thisClass)) === $thisClass) {
            $baseDir = substr($baseDir, 0, -strlen($thisClass));
        }

        $className = ltrim($className, '\\');
        $fileName  = $baseDir;
        $namespace = '';
        if ($lastNsPos = strripos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  .= str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
        if (is_readable($fileName)) {
            require($fileName);
        }
    }

    public static function getManager(Connection $connection)
    {
        return new Objects\Manager($connection);
    }
}
