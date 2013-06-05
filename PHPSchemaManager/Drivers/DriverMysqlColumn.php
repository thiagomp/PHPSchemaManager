<?php
namespace PHPSchemaManager\Drivers;

/**
 *
 * @author thiago
 */
class DriverMysqlColumn
{
  
    // these are all types defined in MySQL
    const BIT = 'BIT';
    const TINYINT = 'TINYINT';
    const SMALLINT = 'SMALLINT';
    const MEDIUMINT = 'MEDIUMINT';
    const INT = 'INT';
    const INTEGER = 'INTEGER';
    const BIGINT = 'BIGINT';
    const REAL = 'REAL';
    const DOUBLE = 'DOUBLE';
    const FLOAT = 'FLOAT';
    const DECIMAL = 'DECIMAL';
    const NUMERIC = 'NUMERIC';
    const DATE = 'DATE';
    const TIME = 'TIME';
    const TIMESTAMP = 'TIMESTAMP';
    const DATETIME = 'DATETIME';
    const YEAR = 'YEAR';
    const CHAR = 'CHAR';
    const VARCHAR = 'VARCHAR';
    const BINARY = 'BINARY';
    const VARBINARY = 'VARBINARY';
    const TINYBLOB = 'TINYBLOB';
    const BLOB = 'BLOB';
    const MEDIUMBLOB = 'MEDIUMBLOB';
    const LONGBLOB = 'LONGBLOB';
    const TINYTEXT = 'TINYTEXT';
    const TEXT = 'TEXT';
    const MEDIUMTEXT = 'MEDIUMTEXT';
    const LONGTEXT = 'LONGTEXT';
    const SET = 'SET';
    const ENUM = 'ENUM';

    const SERIAL = 'auto_increment';

    protected $column;

    function __construct(\PHPSchemaManager\Objects\Column $column)
    {
        $this->column = $column;
    }


    public function getDataDefinition()
    {
        $extraDefinition = "";
        $comment = "'Created by PHPSchemaManager'";

        $columnType = $this->getMysqlColumnTypeString();
        $columnSize = $this->column->getSize();
        $defaultValue = $this->getNormalizedDefaultValue();

        $nullInfo = $this->column->isNullAllowed()                            ? " NULL"     : " NOT NULL";
        $columnSize = empty($columnSize)                                      ? ""          : "($columnSize)";
        $unsigned = !$this->column->isSigned() && $this->column->isNumeric()  ? " UNSIGNED" : "";

        // in case the column is SERIAL, create the column as a PK for the table
        if (\PHPSchemaManager\Objects\Column::SERIAL == $this->column->getType()) {
            $defaultValue    = "";
            $extraDefinition = " AUTO_INCREMENT PRIMARY KEY";
            $nullInfo        = " NOT NULL";
        }

        $sql = "$this->column $columnType{$columnSize}{$unsigned}{$nullInfo}{$defaultValue}{$extraDefinition} COMMENT {$comment}";

        return $sql;
    }

    /**
     * Checks if the size informed reflects the Mysql limits
     * 
     * @param int|string $size It can be a integer or a string with a coma separating the int part from the decimal part
     * @throws \PHPSchemaManager\Exceptions\ColumnMysqlException
     */
    public function validateSize()
    {

        if (\PHPSchemaManager\Objects\Column::FLOAT == $this->column->getType()) {
            $sizeParts = $this->column->getsizeParts();
            if (53 < $sizeParts[0]) {
                throw new \PHPSchemaManager\Exceptions\ColumnMysqlException("Mysql doesn't supports a size bigger than 53 for FLOAT type");
            }
        }
    }

    public function getMysqlColumnTypeString()
    {

        switch ($this->column->getType()) {

            case \PHPSchemaManager\Objects\Column::VARCHAR:
                return self::VARCHAR;

            case \PHPSchemaManager\Objects\Column::CHAR:
                return self::CHAR;

            case \PHPSchemaManager\Objects\Column::TINYTEXT:
                return self::TINYINT;

            case \PHPSchemaManager\Objects\Column::MEDIUMTEXT:
                return self::MEDIUMTEXT;

            case \PHPSchemaManager\Objects\Column::LONGTEXT:
                return self::LONGTEXT;        

            case \PHPSchemaManager\Objects\Column::TEXT:
                return self::TEXT;

            case \PHPSchemaManager\Objects\Column::LONGBLOB:
                return self::LONGBLOB;

            case \PHPSchemaManager\Objects\Column::MEDIUMBLOB:
                return self::MEDIUMBLOB;

            case \PHPSchemaManager\Objects\Column::BLOB:
                return self::BLOB;

            case \PHPSchemaManager\Objects\Column::INT:
            case \PHPSchemaManager\Objects\Column::SERIAL:
                if (3 > $this->column->getSize()) {
                    return self::TINYINT;
                } elseif(6 > $this->column->getSize()) {
                    return self::SMALLINT;
                } elseif(9 > $this->column->getSize()) {
                    return self::INT;
                } elseif (19 > $this->column->getSize()){
                    return self::BIGINT;
                } else {
                    // more info: http://dev.mysql.com/doc/refman/5.0/en/numeric-types.html
                    throw new \PHPSchemaManager\Exceptions\MysqlException("Mysql doesn't accepts number bigger than 18 digitis");
                }

            case \PHPSchemaManager\Objects\Column::FLOAT:
                $size = $this->column->getSize();
                $precision = $decimal = 0;

                if(strpos($size, ",")) {
                    list($precision, $decimal) = explode(',', $size);
                } else {
                    $precision = (int)$size;
                }

                if (24 > $precision) {
                    return self::FLOAT;
                } elseif(54 > $precision) {
                    return self::DOUBLE;
                } else {
                    // more info: http://dev.mysql.com/doc/refman/5.0/en/floating-point-types.html
                    throw new \PHPSchemaManager\Exceptions\MysqlException("Mysql doesn't accepts precision bigger than 53");
                }

            case \PHPSchemaManager\Objects\Column::DECIMAL:
                $size = $this->column->getSize();
                $precision = $decimal = 0;

                if(strpos($size, ",")) {
                    list($precision, $decimal) = explode(',', $size);
                } else {
                    $precision = (int)$size;
                }

                if (65 > $precision) {
                    return self::DECIMAL;
                } else {
                    // more info: http://dev.mysql.com/doc/refman/5.0/en/fixed-point-types.html
                    throw new \PHPSchemaManager\Exceptions\MysqlException("Mysql doesn't accepts precision bigger than 65 for DECIMAL");
                }        

            case \PHPSchemaManager\Objects\Column::DATETIME:
                return self::DATETIME;

            case \PHPSchemaManager\Objects\Column::TIMESTAMP:
                return self::TIMESTAMP;
        }
    }

    /**
     * Map the Mysql types to the types accepted by this library
     * 
     * @param string $type Mysql type defined by this library
     * @return string A Column constant with the equivalent type of this class
     */
    public function mapToLibraryType($type)
    {
        $type = strtoupper($type);
        switch ($type) {
            case self::VARCHAR:
            case self::SET:
            case self::ENUM:
                return \PHPSchemaManager\Objects\Column::VARCHAR;

            case self::CHAR:
                return \PHPSchemaManager\Objects\Column::CHAR;

            case self::TINYTEXT:
                return \PHPSchemaManager\Objects\Column::TINYTEXT;

            case self::MEDIUMTEXT:
                return \PHPSchemaManager\Objects\Column::MEDIUMTEXT;

            case self::LONGTEXT:
                return \PHPSchemaManager\Objects\Column::LONGTEXT;

            case self::TEXT:
                return \PHPSchemaManager\Objects\Column::TEXT;

            case self::LONGBLOB:
                return \PHPSchemaManager\Objects\Column::LONGBLOB;

            case self::MEDIUMBLOB:
                return \PHPSchemaManager\Objects\Column::MEDIUMBLOB;

            case self::BLOB:
                return \PHPSchemaManager\Objects\Column::BLOB;

            case self::INT:
            case self::INTEGER:
            case self::TINYINT:
            case self::MEDIUMINT:
            case self::BIGINT:
            case self::SMALLINT:
                return \PHPSchemaManager\Objects\Column::INT;

            // although MySQL doesn't have this type, the idea is that the mysql class
            // inform this type in case of a auto_increment field is found
            case self::SERIAL:
                return \PHPSchemaManager\Objects\Column::SERIAL;

            case self::FLOAT:
            case self::DOUBLE:
                return \PHPSchemaManager\Objects\Column::FLOAT;

            case self::DECIMAL:
            case self::NUMERIC:
                return \PHPSchemaManager\Objects\Column::DECIMAL;

            case self::DATE:
            case self::DATETIME:
                return \PHPSchemaManager\Objects\Column::DATETIME;

            case self::TIMESTAMP:
            case self::TIME:
                return \PHPSchemaManager\Objects\Column::TIMESTAMP;

            default:
                $msg = "The type $type on the column '$this->column' is not recognized";
                throw new \PHPSchemaManager\Exceptions\ColumnException($msg);
        }
    }

    /**
     * 
     * @param string $type
     * 
     */
    public function setType($type)
    {
        $this->column->setType($this->mapToLibraryType($type));
    }

    protected function getNormalizedDefaultValue() {
        $value = $this->column->getDefaultValue();

        if (\PHPSchemaManager\Objects\Column::NODEFAULTVALUE == $value) {
            $value = '';
        } elseif (\PHPSchemaManager\Objects\Column::NULLVALUE == $value) {
            $value = ' DEFAULT NULL';
        } elseif ("" === $value && $this->column->isNullAllowed()) {
            $value = ' DEFAULT NULL';
        } elseif (is_string($value) && !$this->column->isDefaultLiteral()) {
            $value = " DEFAULT '$value'";
        } elseif (empty($value)) {
            $value = " DEFAULT ''";
        } else {
            $value = " DEFAULT $value";
        }

        return $value;
    }
  
}
