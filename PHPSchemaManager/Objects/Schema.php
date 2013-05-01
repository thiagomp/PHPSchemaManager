<?php
namespace PHPSchemaManager\Objects;

class Schema
  extends Objects
  implements iFather, iObjectEvents {
  
  protected $configuration;
  protected $ignore = FALSE;


  /**
   *
   * @var \PHPSchemaManager\Drivers\iDriver 
   */
  protected $dbms;
  
  protected $tables = array();
  protected $trulyCheckIfHasTable = FALSE;
  
  function __construct($name) {
    $this->setName($name);
  }
  
  /**
   * Get all the tables from this schema
   * 
   * @return Array Array of Table Objects
   */
  public function getTables() {
    return $this->tables;
  }
  
  /**
   * Get one Table object from the informed table
   * 
   * @param string $tableName
   * @return \PHPSchemaManager\Objects\Table|False Returns the Table object if the table exists, False otherwise
   */
  public function hasTable($tableName) {
    // check if the desired table exists in the schema
    foreach ($this->tables as $table) {
      /* @var $table \PHPSchemaManager\Objects\Table */
      
      // take into consideration if this schema is case sensitve or not
      if ($table == $tableName  || (!$this->isCaseSensitiveNamesOn() && strtolower($table) == strtolower($tableName))) {
        
        // check if the table is present even if it's marked to be deleted
        if ($this->trulyCheckIfHasTable) {
          return $table;
        }
        
        // in case the table is marked to be deleted, return FALSE
        return $table->shouldDelete() || $table->isDeleted() ? FALSE : $table;
      }
    }
    
    // the desired table wasn't found in this schema
    return FALSE;
  }
  
  /**
   * Add a new table in the current connection
   * 
   * @param \PHPSchemaManager\Objects\Table $table
   * @param Boolean $replaceTable If TRUE, this method will update the existing table
   */
  public function addTable(Table $table, $replaceTable = FALSE) {
    // check if the table exists in the schema
    if ($oldTable = $this->hasTable($table->getName())) {

      // Check if the table should be replaced in case the library receives a
      // table that already exists in the schema
      if ($replaceTable) {
        //$this->alterTable($table);
        $oldTable->markForDeletion();
        $this->requestFlush();
      }
      else {
        // if the table is not to be replaced, throws an Exception
        $msg = "Table '$table' already exists." . PHP_EOL . 
                "It is possible to replace the table, by using the \$replaceTable parameter. " .
                "But if the database doesn't support ALTER without destryoing the data, you might loose all your table data.";
        throw new \PHPSchemaManager\Exceptions\SchemaException($msg);
      }
    }
    
    // in case the table wasn't found in the schema, now is the time to add it

    // Check if the table have at least one column.
    if (!$table->countColumns()) {
      $msg = "A table must have at least one column before creation";
      throw new \PHPSchemaManager\Exceptions\SchemaException($msg);
    }

    // Before create, check if the table is still on the tables class variable
    // in case it is, cause a flush, then, create the table
    // This situation might happen when the user marked a table for deletion and
    // tries to create a table with the same name before sending a flush
    $t = $this->trulyHasTable($table->getName());
    if ($t instanceof Table) {
      //check if the table should be deleted...
      if ($t->shouldDelete()) {
        // ... if yes, send a flush to remove the table from the database
        $this->father->getConnection()->driver->flush($this);
        // and after this, create the new table that have the same name
      }
    }
    $table->markForCreation();
    $table->setFather($this);

    // table is ready to be added to the schema object
    $this->tables[] = $table;
  }
  
  public function dropTable($tableName) {
    $table = $this->hasTable($tableName);
    if (FALSE !== $table) {
      $table->drop();
    }
    else {
      throw new \PHPSchemaManager\Exceptions\SchemaException("Table $tableName couldn't be dropped since it wasn't found in the schema '$this'");
    }
    
    return TRUE;
  }
 
  public function informChange() {
    // do nothing
  }
  
  public function informDeletion(Objects $object) {
    if ($object instanceof Table) {
      $this->removeTable($object);
    }
  }
  
  public function onDelete() {
    foreach($this->getTables() as $table) {
      /* @var $table \PHPSchemaManager\Objects\Table */
      $table->markForDeletion();
    }
  }
  
  public function onDestroy() {
    foreach($this->getTables() as $table) {
      /* @var $table \PHPSchemaManager\Objects\Table */
      $table->markAsDeleted();
      $table->destroy();
    }
  }
  
  /**
   * Count how many tables the database/schema has
   * 
   * @return integer
   */
  public function countTables() {
    return count($this->tables);
  }
  
  public function ignore() {
    $this->ignore = TRUE;
  }
  
  public function regard() {
    $this->ignore = FALSE;
  }

  public function shouldBeIgnored() {
    return $this->ignore;
  }
  
  public function saveSchemaJSON($filePath) {
    $json = $this->printJSON();
    if(FALSE === file_put_contents($filePath, $json)) {
      throw new \PHPSchemaManager\Exceptions\SchemaException("Unable to write JSON file at $filePath");
    }
  }

  public function flush() {
    /* @var $conn \PHPSchemaManager\Connection */
    $conn = $this->father->getConnection();
    
    $conn->driver->flush($this);
  }
  
  /**
   * returns a text representation of the Schema
   * 
   * @return string
   */
  public function printTxt() {
    $tables = $this->getTables();
    $str = "Tables from {$this} (".count($tables)." tables found)" . 
            " [" . $this->getAction() . "]" . PHP_EOL;
    foreach($tables as $table) {
      $str .= $table->printTxt() . PHP_EOL;
    }
    
    return $str;
  }
  
  /**
   * returns a JSON representation of the Schema
   * 
   * @return string
   */
  public function printJSON() {
    $json = "{" . PHP_EOL;
    $json .= "  \"$this\": {" . PHP_EOL;
    foreach($this->getTables() as $table) {
      /* @var $table \PHPSchemaManager\Objects\Table */
      $json .= $table->printJSON(4);
    }
    $json = substr($json, 0, -1*(strlen(PHP_EOL)*2+1)) . PHP_EOL . PHP_EOL;
    $json .= "  }" . PHP_EOL . "}" . PHP_EOL;
    
    return $json;
  }
  
  public function __toString() {
    return $this->getName();
  }
  
  protected function alterTable(Table $newTable) {
    foreach ($this->tables as $idx => $currentTable) {
      /* @var $currentTable \PHPSchemaManager\Objects\Table */
      if ($newTable->getName() == $currentTable->getName()) {
        
        // compare the columns. For the existing ones, alter the missing, remove
        foreach($currentTable->getColumns() as $currentColumn) {
          /* @var $newColumn \PHPSchemaManager\Objects\Column */
          
          // assumes the Column will not be found
          $columnFound = FALSE;
          
          foreach($newTable->getColumns() as $newColumn) {
            /* @var $currentColumn \PHPSchemaManager\Objects\Column */
            if ($newColumn->getName() == $currentColumn->getName()) {
              
              // To be able to set a Object to be altered, it must be first in the synced stated
              // that's why I'm using the setAction method directly
              $newColumn->setAction(self::ACTIONALTER);
              $columnFound = TRUE;
              break;
            }
          }
          
          // if the column is not found in the new table ...
          if (!$columnFound) {
            // ... injects the column in the new table, but indicates it to be removed
            $currentColumn->markForDeletion();
            $newTable->addColumn($currentColumn);
          }
        }
        
        // check if there are PRIMARY
        
        $newTable->setAction(self::ACTIONALTER);
        $newTable->setFather($this);
        $this->tables[$idx] = $newTable;
        return TRUE;
      }
    }
    
    return FALSE;
  }
  
  protected function removeTable(Table $table) {
    foreach($this->tables as $idx => $currentTable) {
      if ($table->getName() == $currentTable->getName()) {
        unset($this->tables[$idx]);
        return TRUE;
      }
    }
    
    throw new \PHPSchemaManager\Exceptions\SchemaException("Table $table couldn't be removed from the schema '$schema'");
  }
  
  protected function trulyHasTable($tableName) {
    $this->trulyCheckIfHasTable = TRUE;
    $res = $this->hasTable($tableName);
    $this->trulyCheckIfHasTable = FALSE;
    return $res;
  }
}