<?php
namespace PHPSchemaManager\Objects;

/**
 * Description of Table
 *
 * @author thiago
 */
class Table extends Objects implements FatherInterface, ObjectEventsInterface
{

    protected $columns = array();
    protected $indexes = array();
    protected $tableSpecifics = array();
    protected $trulyCheckIfHasObject = false;

    public function __construct($tableName)
    {
        $this->setName($tableName);
        $this->markForCreation();
    }

    /**
     * Adds a column to this table
     *
     * @param \PHPSchemaManager\Objects\Column $column Column being added in the table
     */
    public function addColumn(Column $column)
    {

        // Before create, check if the column is still on the tables class variable
        // in case it is, cause a flush, then, create the table
        // This situation might happen when the user marked a table for deletion and
        // tries to create a table with the same name before sending a flush
        $oldColumn = $this->trulyHasObject($column);
        if (!empty($oldColumn)) {
            if ($oldColumn->shouldDelete()) {
                $this->requestFlush();
            }
        }

        // check if the the column is from SERIAL data type
        if (\PHPSchemaManager\Objects\Column::SERIAL ==  $column->getType()) {
            // check if the table already have a index named 'PRIMARY'
            if (!$this->hasIndex('PRIMARY')) {
                // in case there no index, let's create one
                $index = new Index('PRIMARY');
                $index->addColumn($column);
                $index->setAsPrimaryKey();

                $this->addIndex($index);
            }
        }

        $column->setFather($this);
        $this->columns[] = $column;

        $this->markForAlter();
    }

      /**
     * Get all columns from this table
     *
     * @return array Return an Array of Columns
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Check if the Table has the column informed in the parameter
     *
     * @param string $columnName Column name to be searched
     * @return \PHPSchemaManager\Objects\Column|boolean It returns the Column object if the column is found or FALSE in
     *   case the column is not found
     */
    public function hasColumn($columnName)
    {
        return $this->hasObject($columnName, 'column');
    }

    /**
     * Drop a column from the table
     *
     * @param string $columnName Name of the column that will be dropped from the table
     * @return boolean
     * @throws \PHPSchemaManager\Exceptions\TableException
     */
    public function dropColumn($columnName)
    {
        $column = $this->hasColumn($columnName);
        if (false !== $column) {
            $column->drop();
        } else {
            $msg = "Column $columnName can't be dropped since it wasn't found in the table $this";
            throw new \PHPSchemaManager\Exceptions\TableException($msg);
        }

        return true;
    }

    /**
     * Add a index to this table
     *
     * @param \PHPSchemaManager\Objects\Index $index
     */
    public function addIndex(Index $index)
    {

        // Check if the index has at least one column in it.
        if (1 > $index->countColumns()) {
            $msg = "Invalid Index association. Indexes must have at least one "
                    ."column before adding them to a table";
            throw new \PHPSchemaManager\Exceptions\TableException($msg);
        }
        
        /* @var $oldIndex \PHPSchemaManager\Objects\Index */
        $oldIndex = $this->trulyHasObject($index);

        // Check if the Index already exists
        if (!empty($oldIndex)) {
            // The informed index already exists in the table...

            // If the Index is a PK and in the Table object already have a PK defined
            // remove the one in the Table object and go with the new index informed
            if ($index->isPrimaryKey() && $oldIndex->isPrimaryKey()) {
                $this->informDeletion($oldIndex);
                $oldIndex->markAsDeleted();
            } else {
                $oldIndex->markForDeletion();
                // ...in case it's marked to be deleted, request a flush to remove it
                $this->requestFlush();
            }
        }

        // configure this table as the father of the index
        $index->setFather($this);

        // add the index in the indexes list
        $this->indexes[] = $index;

        $this->markForAlter();

    }

    public function getIndexes()
    {
				// Regenerates the index to start always from zero
				//TODO take into consideration the order of the indexes in the database
        return array_values($this->indexes);
    }

    /**
     * Check if the Table has the index informed in the parameter
     *
     * @param string $indexName Index name to be searched
     * @return \PHPSchemaManager\Objects\Index|boolean It returns the Index object if the index is found or FALSE in
     *   case the index is not found
     */
    public function hasIndex($indexName)
    {
        return $this->hasObject($indexName, 'index');
    }

    /**
     * Drop an Index from the table
     *
     * @param string $indexName
     * @return boolean
     * @throws \PHPSchemaManager\Exceptions\TableException
     */
    public function dropIndex($indexName)
    {
        $index = $this->hasIndex($indexName);
        if (false !== $index) {
            $index->drop();
        } else {
            $msg = "Index $indexName can't be dropped since it wasn't found in the table $this";
            throw new \PHPSchemaManager\Exceptions\TableException($msg);
        }

        return true;
    }

    /**
     * Return the index if it is found or FALSE incase there's no primary key
     *
     * @return \PHPSchemaManager\Objects\Index|boolean
     */
    public function hasPrimaryKey()
    {
        foreach ($this->getIndexes() as $index) {
            /* @var $index \PHPSchemaManager\Objects\Index */
            if ($index->isPrimaryKey()) {
                return $index;
            }
        }

        return false;
    }

    public function addSpecificConfiguration(\PHPSchemaManager\Drivers\TableSpecific $specifics)
    {
        $this->tableSpecifics[] = $specifics;
    }

    public function getSpecificsConfiguration()
    {
        return $this->tableSpecifics;
    }

    public function informChange()
    {
        $this->markForAlter();
    }

    public function informDeletion(Objects $object)
    {
        if ($object instanceof Column) {
            $this->removeColumn($object);
        } elseif ($object instanceof Index) {
            $this->removeIndex($object);
        }
    }
    
    public function informSynced()
    {
        foreach ($this->getColumns() as $column) {
            /* @var $column \PHPSchemaManager\Objects\Column */
            if (!$column->isSynced()) {
                $this->informChange();
                return;
            }
        }
        
        foreach ($this->getIndexes() as $index) {
            /* @var $index \PHPSchemaManager\Objects\Index */
            if (!$index->isSynced()) {
                $this->informChange();
                break;
            }
        }
    }

    public function onDelete()
    {
        foreach ($this->getColumns() as $column) {
            /* @var $column \PHPSchemaManager\Objects\Column */
            $column->markForDeletion();
        }

        foreach ($this->getIndexes() as $index) {
            /* @var $index \PHPSchemaManager\Objects\Index */
            $index->markForDeletion();
        }
    }

    public function onDestroy()
    {
        foreach ($this->getColumns() as $column) {
            /* @var $column \PHPSchemaManager\Objects\Column */
            $column->markAsDeleted();
        }

        foreach ($this->getIndexes() as $index) {
            /* @var $index \PHPSchemaManager\Objects\Index */
            $index->markAsDeleted();
        }
    }

    public function persisted()
    {
        $this->persistColumns();
        $this->persistIndexes();
        parent::persisted();
        if ($father = $this->getFather()) {
            $father->persisted();
        }
    }

    public function countColumns()
    {
        return count($this->columns);
    }

    public function countIndexes()
    {
        return count($this->indexes);
    }

    /**
     * returns a text representation of the Table
     *
     * @return string
     */
    public function printTxt()
    {
        $str = "{$this} [{$this->getAction()}]" . PHP_EOL;
        $columns = $this->getColumns();
        foreach ($columns as $column) {
            $str .= "  {$column->printTxt()}" . PHP_EOL;
        }

        $str .= "  " . str_repeat(".", 28) . PHP_EOL;
        $indexes = $this->getIndexes();
        if (!empty($indexes)) {
            $str .= "  indexes" . PHP_EOL;
            foreach ($indexes as $index) {
                $str .= $index->printTxt();
            }
        } else {
            $str .= "  no indexes" . PHP_EOL;
        }

        $str .= "  " . str_repeat(".", 28) . PHP_EOL;
        $specificConfiguration = $this->getSpecificsConfiguration();
        if (!empty($specificConfiguration)) {
            $str .= "  specifics" . PHP_EOL;
            foreach ($specificConfiguration as $specifics) {
                $str .= $specifics->printTxt();
            }
        } else {
            $str .= "  no specifics" . PHP_EOL;
        }

        $str .= str_repeat("-", 30) . PHP_EOL . PHP_EOL;
        return $str;
    }

    /**
     * returns a JSON representation of the Table
     *
     * @param int $spaces Amount of spaces will be placed in the begining of the string
     * @return string
     */
    public function printJSON($spaces = 0)
    {
        $json = str_repeat(" ", $spaces) . "\"$this\": {" . PHP_EOL;

        // get table columns
        $json .= str_repeat(" ", $spaces) . "  \"columns\": {" . PHP_EOL;

        foreach ($this->getColumns() as $column) {
            /* @var $column \PHPSchemaManager\Objects\Column */
            $json .= $column->printJSON($spaces + 4);
        }

        $json = substr($json, 0, -1*(strlen(PHP_EOL)+1)) . PHP_EOL;
        $json .= str_repeat(" ", $spaces) . "  }," . PHP_EOL . PHP_EOL;

        // get table keys
        $json .= str_repeat(" ", $spaces) . "  \"keys\": {" . PHP_EOL;
        foreach ($this->getIndexes() as $index) {
            /* @var $index \PHPSchemaManager\Objects\Index */
            $json .= $index->printJSON($spaces + 4);
        }
        $json = substr($json, 0, -1*(strlen(PHP_EOL)+1)) . PHP_EOL;
        $json .= str_repeat(" ", $spaces) . "  }" . PHP_EOL;
        $json .= str_repeat(" ", $spaces) . "}," . PHP_EOL . PHP_EOL;

        return $json;
    }

    public function __toString()
    {
        return $this->getName();
    }

    protected function hasObject($objectName, $objectType)
    {

        if ($objectType == 'column') {
            $objects = $this->getColumns();
        } else {
            $objects = $this->getIndexes();
        }

        foreach ($objects as $object) {
            if ($object == $objectName) {
                if ($this->trulyCheckIfHasObject) {
                    return $object;
                }
                return $object->shouldDelete() || $object->isDeleted() ? false : $object;
            }
        }

        return false;
    }

    protected function persistColumns()
    {

        foreach ($this->getColumns() as $column) {

            // check if the column must be removed from the table object
            if ($column->shouldDelete()) {
                $column->markAsDeleted();
                $column->destroy();
            } else {
                // inform that all other columns are now synced
                $column->persisted();
            }
        }
    }

    protected function persistIndexes()
    {

        foreach ($this->getIndexes() as $index) {

            // check if the column must be removed from the table object
            if ($index->shouldDelete()) {
                $index->markAsDeleted();
                $index->destroy();
            } else {
                // inform that all other columns are now synced
                $index->persisted();
            }
        }
    }

    /**
     * Remove the Column object from this table.
     *
     * @param \PHPSchemaManager\Objects\Column $column
     * @return boolean
     * @throws \PHPSchemaManager\Exceptions\TableException
     */
    protected function removeColumn(\PHPSchemaManager\Objects\Column $column)
    {
        foreach ($this->columns as $idx => $currentColumn) {
            if ($column->getName() == $currentColumn->getName()) {
                unset($this->columns[$idx]);
                return true;
            }
        }

        $msg = "Column $column couldn't be removed from the Table object, because it wasn't found in the table $this";
        throw new \PHPSchemaManager\Exceptions\TableException($msg);
    }

    protected function removeIndex(\PHPSchemaManager\Objects\Index $index)
    {
        foreach ($this->indexes as $idx => $currentIndex) {
            if ($index->getName() == $currentIndex->getName()) {
                unset($this->indexes[$idx]);
                return true;
            }
        }

        $msg = "Index $index couldn't be removed from the Table object, because it wasn't found in the table $this";
        throw new \PHPSchemaManager\Exceptions\TableException($msg);
    }

    protected function trulyHasObject(Objects $object)
    {
        $this->trulyCheckIfHasObject = true;
        $className = join('', array_slice(explode('\\', strtolower(get_class($object))), -1));
        $res = $this->hasObject($object->getName(), $className);
        $this->trulyCheckIfHasObject = false;
        return $res;
    }

    protected function listenTo(Objects $object)
    {
        $object->addListener($this);
    }
}
