<?php
namespace PHPSchemaManager\Drivers;

class Driver
{
    protected static $dbbo;
  
    const MYSQL = 'mysql';
  
    public static function getDbms(\PHPSchemaManager\Connection $conn)
    {
    
        switch ($conn->dbms)
        {
            case self::MYSQL:
                return new DriverMysql($conn);
            default:
                throw new \Exception($conn->dbms . ' is not supported');
        }
    }
}
