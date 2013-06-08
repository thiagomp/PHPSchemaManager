<?php
namespace PHPSchemaManager\Objects;

/**
 * Description of Index
 *
 * @author thiago
 */
class Index extends Objects implements iObjectEvents
{

    protected $indexName;

    /**
     *
     * @var string One of these values: unique, pk, regular
     */
    protected $type;

    /**
     *
     * @var \PHPSchemaManager\Objects\Column Columns that compose this index. The order of the index is determined by
     *   the index of this array
     */
    protected $columns;

    /**
     *
     * @var \PHPSchemaManager\Objects\Column Column that is the PK . This is just an index of the column in the
     *   $columns variable
     */
    protected $primaryColumnIndex = false;


    const REGULAR = 'regular';
    const PRIMARYKEY = 'pk';
    const UNIQUE = 'unique';

    public function __construct($indexName)
    {
        $this->setName($indexName);
        $this->setAsRegularKey();
        $this->markForCreation();
    }

    /**
     * Tells the typs of this index
     *
     * @param string $type Must be one of these values: unique, pk, regular
     * @throws \PHPSchemaManager\Exceptions\IndexException
     */
    public function setType($type)
    {
        $allowedTypes = array(self::REGULAR, self::UNIQUE, self::PRIMARYKEY);

        if (false === array_search($type, $allowedTypes)) {
            throw new \SchemaManager\Exceptions\IndexException("Index type $type is not supported by this library");
        }

        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * Add a column to this index
     *
     * @param \PHPSchemaManager\Objects\Column $column
     * @param int $index
     */
    public function addColumn(Column $column)
    {
        $this->columns[] = $column;
        $this->markForAlter();
        return array_search($column, $this->columns);
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function setAsPrimaryKey()
    {
        $this->setType(self::PRIMARYKEY);
    }

    public function setAsUniqueKey()
    {
        $this->setType(self::UNIQUE);
    }

    public function setAsRegularKey()
    {
        $this->setType(self::REGULAR);
    }

    public function isPrimaryKey()
    {
        return self::PRIMARYKEY == $this->type;
    }

    public function isUniqueKey()
    {
        return self::UNIQUE == $this->type;
    }

    public function isRegularKey()
    {
        return self::REGULAR == $this->type;
    }

    /**
     * How many columns this index holds
     *
     * @return int
     */
    public function countColumns()
    {
        return count($this->columns);
    }

    public function onDelete()
    {
        $this->father->markForAlter();
    }

    public function onDestroy()
    {
        //do nothing
    }

    public function printTxt()
    {
        $str = "  $this: {$this->getType()} ";
        $indexColumns = $this->getColumns();

        $strCols = array();
        foreach ($indexColumns as $indexCol) {
            $strCols[] = $indexCol;
        }

        $strCols = implode(", ", $strCols);
        $str .= "($strCols) [{$this->getAction()}]" . PHP_EOL;

        return $str;
    }

    public function printJSON($spaces = 0)
    {
        $json = '';

        $json .= str_repeat(" ", $spaces) . "\"$this\": {" . PHP_EOL;
        $json .= str_repeat(" ", $spaces) . "  \"type\": \"{$this->getType()}\"," . PHP_EOL;
        $json .= str_repeat(" ", $spaces) . "  \"columns\": [";
        foreach ($this->getColumns() as $indexColumn) {
            $json .= "\"$indexColumn\", ";
        }
        $json = substr($json, 0, -2) . "]" . PHP_EOL;
        $json .= str_repeat(" ", $spaces) . "}," . PHP_EOL;

        return $json;
    }

    public function __toString()
    {
        return $this->getName();
    }
}
