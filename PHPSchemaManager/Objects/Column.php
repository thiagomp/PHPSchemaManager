<?php
namespace PHPSchemaManager\Objects;

/**
 * Description of Column
 *
 * @author thiago
 */
class Column extends Objects implements iObjectEvents
{

    protected $name = '';
    protected $type;
    protected $null;
    protected $defaultValue;
    protected $defaultValueLiteral;
    protected $signedInt;
    protected $sizeParts;

    protected $size = 0;

    // Generic types supported by this library
    const VARCHAR = 'varchar';
    const CHAR = 'char';

    const TINYTEXT = 'tinytext';
    const MEDIUMTEXT = 'mediumtext';
    const LONGTEXT = 'longtext';
    const TEXT = 'text';
    const LONGBLOB = 'longblob';
    const MEDIUMBLOB = 'mediumblob';
    const BLOB = 'blob';

    const INT = 'int';
    const SERIAL = 'serial';

    const FLOAT = 'float';
    const DECIMAL = 'decimal';

    const DATETIME = 'datetime';
    const TIMESTAMP = 'timestamp';


    // special values supported by this library
    const NULLVALUE = 'schemamanagernullvalue';
    const CUSTOMVALUE = 'schemamanagercustomvalue'; //TODO not supported yet, left here for future implementation
    const NODEFAULTVALUE = 'schemamanagernodefaultvalue';

    public function __construct($columnName)
    {
        $this->setName($columnName);
        $this->markForCreation();
        $this->allowsNull();
        $this->setDefaultValue(self::NODEFAULTVALUE);

        // Although is not possible to determine if the column is a numeric type
        // this class will assume that the number is not signed by default
        // In case the type gets a non-numeric type, no problem, since the signed
        // information will be ignored when generating the SQL to manage the column
        // in the database
        $this->signed();
    }

    /**
     * Set the generic column type
     *
     * @param string $type Use one of the defined constants on this class to define the type
     * @throws ColumnException
     */
    public function setType($type)
    {
        $intTypes = $this->getNumericTypes();
        $strTypes = $this->getStringTypes();

        $supportedTypes = array(self::BLOB, self::DATETIME, self::LONGBLOB,
            self::LONGTEXT, self::MEDIUMTEXT, self::TEXT, self::TINYTEXT,
            self::TIMESTAMP);

        $supportedTypes = array_merge($supportedTypes, $intTypes, $strTypes);

        // check if the type is empty... it cannot be empty
        if (empty($type)) {
            throw new \PHPSchemaManager\Exceptions\ColumnException("Type cannot be empty");
        }

        // check if the type is any of the supported types of this library
        if (false === array_search($type, $supportedTypes)) {
            throw new \PHPSchemaManager\Exceptions\ColumnException("Type $type is not supported by this library");
        }

        // save the type
        $this->type = $type;

        $obj = $this->typeStrategy();
        if (!empty($obj)) {
            $obj->configure();
        }

        $this->markForAlter();
    }

    public function getType()
    {
        return $this->type;
    }

    public function setSize($size)
    {
        $sizeParts = array(0,0);

        // FLOAT and DECIMAL types have a special way to inform their size
        if (self::FLOAT == $this->getType() ||  self::DECIMAL == $this->getType()) {
            $matches = array();
            if (!preg_match('/^(\d+)(,\d+)?$/', $size, $matches)) {
                $msg = "The informed size $size is not supported by columns of {$this->getType()} type";
                throw new \PHPSchemaManager\Exceptions\ColumnException($msg);
            }

            // in case only 7 was informed, the value will be normalized into 7,0
            if (!isset($matches[2])) {
                $sizeParts = array((int)$size, 0);
                $size = "$size,0";
            } else {
                $sizeParts = array((int)$matches[1], (int)$matches[2]);
            }

        } else {
            // normalize the size variable to be integer
            $size = (int)$size;
        }

        $this->size = $size;
        $this->sizeParts = $sizeParts;

        $this->markForAlter();
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getSizeParts()
    {
        return $this->sizeParts;
    }


    /**
     * Configure the column to accept NULL values
     */
    public function allowsNull()
    {
        $this->null = true;
        $this->setDefaultValue(self::NULLVALUE);
        $this->markForAlter();
    }

    /**
     * Configure the column to not hold NULL values
     */
    public function forbidsNull()
    {
        $this->null = false;

        // if the current default value for this column is NULL, it will be set to empty
        if (self::NULLVALUE == $this->getDefaultValue()) {
            $this->setDefaultValue("");
        }

        $this->markForAlter();
    }

    /**
     * Check if Null value is allowed for this column
     *
     * @return Boolean
     */
    public function isNullAllowed()
    {
        return $this->null;
    }

    /**
     * Default value, in case NULL is passed to the new/updated row
     *
     * @param mixed $value
     * @param string $literalValue It helps when you send to use a function or variable.
     * I.e.: DEFAULT CURRENT_TIMESTAMP. In this case you should use $obj->setDefaultValue('CURRENT_TIMESTAMP', TRUE);
     */
    public function setDefaultValue($value, $literalValue = false)
    {

        // check if the column allows NULL value
        if (null === $value || self::NULLVALUE == $value) {
            if ($this->isNullAllowed()) {
                $value = self::NULLVALUE;
            } else {
                // the column doesn't accepts NULL value, configures it to have no default value on it
                $value = self::NODEFAULTVALUE;
            }
        }

        // check if the informed default value is under the permited size
        if (self::NULLVALUE != $value && self::CUSTOMVALUE != $value && self::NODEFAULTVALUE != $value) {

            $sizeTypes = array_merge($this->getNumericTypes(), $this->getStringTypes());
            if (false !== array_search($this->getType(), $sizeTypes)) {
                if (mb_strlen($value) > $this->getSize()) {
                    $msg = "The informed default value [{$value}] for column '$this' is bigger " .
                        "[".(mb_strlen($value))."] than the size defined for this column allows [{$this->getSize()}]";
                    throw new \PHPSchemaManager\Exceptions\ColumnException($msg);
                }
            }
        }

        $this->defaultValue = $value;
        $this->defaultValueLiteral = $literalValue;
        $this->markForAlter();
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function isDefaultLiteral()
    {
        return $this->defaultValueLiteral;
    }

    /**
     * Call this function if the value must be signed.
     * This just make sense if the type is a number
     */
    public function signed()
    {
        $this->signedInt = true;
        $this->markForAlter();
    }

    /**
     * Call this function if the value must be unsigned.
     * This just make sense if the type is a number
     */
    public function unsigned()
    {
        $this->signedInt = false;
        $this->markForAlter();
    }

    /**
     * Check if the value is Signed or not.
     * It just makes sense if this is a number column
     *
     * @return Boolean
     */
    public function isSigned()
    {
        return $this->signedInt;
    }

    public function getNumericTypes()
    {
        return array(self::DECIMAL, self::INT, self::SERIAL, self::FLOAT);
    }

    public function getStringTypes()
    {
        return array(self::CHAR, self::VARCHAR);
    }

    /**
     * Check if the column is a Numeric type
     *
     * @return boolean
     */
    public function isNumeric()
    {
        if (false !== array_search($this->getType(), $this->getNumericTypes())) {
            return true;
        }

        return false;
    }

    public function typeStrategy()
    {
        switch ($this->getType()) {
            case self::SERIAL:
                return new ColumnTypeStrategySerial($this);
        }
    }

    /**
     * Check if the column is one of the String types possible for the column
     *
     * @return Boolean
     */
    public function isStringType()
    {
        return in_array($this->getType(), $this->getStringTypes());
    }

    /**
     * Check if the column is one of the Numeric types possible for the column
     *
     * @return Boolean
     */
    public function isNumericType()
    {
        return in_array($this->getType(), $this->getNumericTypes());
    }

    public function onDelete()
    {
        //TODO maybe there's a need to inform Index that a column is being deleted
        //do nothing
    }

    public function onDestroy()
    {
        //do nothing
    }

    /**
     * returns a text representation of the Column
     *
     * @return string
     */
    public function printTxt()
    {

        // normalizes the default value to have a better presentation when printed
        $defaultValue = $this->getNormalizedDefaultValue();
        if ($this->isStringType()) {
            $defaultValue = "'$defaultValue'";
        }

        return "$this: {$this->getType()}({$this->getSize()}), " .
                ($this->isNullAllowed() ? "NULL" : "NOT NULL") .
                ", {$defaultValue} [{$this->getAction()}]";
    }

    /**
     * returns a JSON representation of the Column
     *
     * @param int $spaces Amount of spaces will be placed in the begining of the string
     * @return string
     */
    public function printJSON($spaces = 0)
    {
        $json = '';

        // in the JSON file, we will save the NULL value as empty
        if ($this->getNormalizedDefaultValue() == 'NULL') {
            $defaultValue = '';
        } else {
            $defaultValue = $this->getNormalizedDefaultValue();
        }

        $spaceStrings = str_repeat(" ", $spaces);
        $json .= $spaceStrings . "\"$this\": {" . PHP_EOL;
        $json .= $spaceStrings . "  \"type\": \"{$this->getType()}\"," . PHP_EOL;
        $json .= $spaceStrings . "  \"size\": \"{$this->getSize()}\"," . PHP_EOL;
        $json .= $spaceStrings . "  \"allowNull\": \"" . ($this->isNullAllowed() ? 'yes' : 'no') . "\"," . PHP_EOL;
        $json .= $spaceStrings . "  \"defaultValue\": \"$defaultValue\"" . PHP_EOL;
        $json .= $spaceStrings . "}," . PHP_EOL;

        return $json;
    }

    public function __toString()
    {
        return $this->getName();
    }

    protected function getNormalizedDefaultValue()
    {

        $defaultValue = $this->getDefaultValue();

        // normalizes the column default value
        switch ($defaultValue) {
            case self::NODEFAULTVALUE:
                return '';
                break;
            case self::NULLVALUE:
                return 'NULL';
                break;
            default:
                return $defaultValue;
        }
    }
}
