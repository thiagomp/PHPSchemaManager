<?php
namespace PHPSchemaManager\Drivers;

class DriverMysql
  implements iDriver {
  
  protected $sm;
  protected $conn;
  protected $databaseSelected = FALSE;
  protected $linkIdentifier;


  public function __construct(\PHPSchemaManager\Connection $conn) {
    $this->conn = $conn;
  }
  
  
  // Methods from the interface
  public function connect() {
    if (empty($this->linkIdentifier)) {
      if (!$linkIdentifier = mysql_connect($this->conn->hostname, $this->conn->username, $this->conn->password)) {
        throw new \SchemaManager\Exceptions\MysqlException("Failed to connect on {$this->conn->dbms} at {$this->conn->hostname} with {$this->conn->username} user");
      }

      $this->linkIdentifier = $linkIdentifier;
    }

    return $this->linkIdentifier;
  }
  
  public function selectDb($dbName = null) {
    
    if (empty($dbName)) {
      $dbName = $this->getDatabaseSelected();
    }
    
    if ($this->databaseSelected != $dbName) {
      if(!mysql_select_db($dbName)) {
        $msg = "Database '$dbName' wasn't found, you have to create it first";
        throw new \SchemaManager\Exceptions\MysqlException($msg);
      }

      $this->databaseSelected = $dbName;
    }
    
    return $dbName;
  }
  
  public function getDatabaseSelected() {
    return $this->databaseSelected;
  }
  
  public function getSchemas(Array $ignoredSchemas = NULL) {
    $schemas = array();
    
    // check how this environment should operate
    $lowerCaseTableNames = $this->checkLowerCaseTableNames();
    
    // get the list of databases found in this connection
    $res = mysql_list_dbs();
    while($row = mysql_fetch_array($res)) {
      $schema = new \PHPSchemaManager\Objects\Schema($row['Database']);
      
      // configure the schema to operate according to how this environment should work
      $lowerCaseTableNames ? $schema->turnCaseSensitiveNamesOn() : $schema->turnCaseSensitiveNamesOff();
      
      // check if the schema should be ignored
      if (!in_array($schema->getSchemaName(), $ignoredSchemas)) {
        $this->getTables($schema);
      }
      else {
        // ignores this schema
        $schema->ignore();
      }
      
      $schema->persisted();
      
      $schemas[] = $schema;
    }
    
    return $schemas;
  }
  
  public function getTables(\PHPSchemaManager\Objects\Schema $schema) {
   
    try {
      $this->selectDb($schema->getSchemaName());
    }catch(\PHPSchemaManager\Exceptions\MysqlException $e){
      // most probably, it because the schema wasn't created yet
      // in this case, an empty set of tables will be replied
      return array();
    }
    
    $tables = array();
    
    // get the tables from the database
    $sql = "SHOW TABLES";
    $res = $this->dbQuery($sql);
    while($row = mysql_fetch_row($res)) {
      $table = new \PHPSchemaManager\Objects\Table($row[0]);
      
      // get the columns and put them into the table object directly
      $this->getColumns($table);
      
      // get the indexes
      $this->getIndexes($table);
      
      $table->persisted();
      
      $schema->addTable($table);
    }
  }
  
  public function dbQuery($sql){
    
    $result = mysql_query($sql);

    if (!$result) {
        $msg = 'MySQL Error: ' . mysql_error() . "\nQuery: $sql";
        throw new \PHPSchemaManager\Exceptions\MysqlException($msg);
    }
    
    return $result;
  }

  public function dbFetchArray($result) {
    return mysql_fetch_assoc($result);
  }
  
  public function getCreateTableStatement(\PHPSchemaManager\Objects\Table $table) {
    $sql = "SHOW CREATE TABLE $table";
    $result = $this->dbQuery($sql);
    $row = $this->dbFetchArray($result);
    return $row["Create Table"];
  }
  
  public function getTableCount($table) {
    $this->selectDb();
    
    $sql = "SELECT COUNT(*) AS num_rows FROM $table";
    $res = $this->dbFetchArray($this->dbQuery($sql));
    return (int)$res['num_rows'];
  }
  
  public function flush(\PHPSchemaManager\Objects\Schema $schema) {
    // if the schema should be ignored, just mark it as synced and move on
    if ($schema->shouldBeIgnored()) {
      $schema->persisted();
      return TRUE;
    }
    
    // flush schema
    switch($schema->getAction()) {
      case \PHPSchemaManager\Objects\Schema::ACTIONCREATE:
        $this->createDatabase($schema->getSchemaName());
        break;

      case \PHPSchemaManager\Objects\Schema::ACTIONALTER:
        //TODO implement changes requested for the schema
        break;

      case \PHPSchemaManager\Objects\Schema::ACTIONDELETE:
        $this->dropDatabase($schema);
        
        // Since the schema was dropped, we don't need to do anything below
        // with the tables
        return TRUE;

      case \PHPSchemaManager\Objects\Schema::STATUSSYNCED:
        // nothing to do
        break;

      default:
        throw new \PHPSchemaManager\Exceptions\MysqlException("Action {$table->getAction()} is not implemented by this library");
    }
    
    // schema is now ready to be used
    $schema->persisted();
    
    // switch to the schema, so the tables can be also persisted
    $this->selectDb($schema->getSchemaName());
    
    // flush tables
    foreach($schema->getTables() as $table) {
      /* @var $table \SchemaManager\Objects\Table */
      switch($table->getAction()) {
        case \PHPSchemaManager\Objects\Table::ACTIONALTER:
          $this->alterTable($table);
          $table->persisted();
          break;
        
        case \PHPSchemaManager\Objects\Table::ACTIONCREATE:
          $this->createTable($table);
          
          // after creating the table, refresh the Indexes, because of the SERIAL type
          $this->getIndexes($table);
          $table->persisted();
          break;
        
        case \PHPSchemaManager\Objects\Table::ACTIONDELETE:
          $this->deleteTable($table);
          break;
        
        case \PHPSchemaManager\Objects\Table::STATUSSYNCED:
          // nothing to do
          break;
        
        default:
          throw new \PHPSchemaManager\Exceptions\MysqlException("Action {$table->getAction()} is not implemented by this library");
      }
      
    }
    
    // the schema and its tables are now persisted
    return TRUE;
  }
  
  // Methods specific for MySQL
  protected function getColumns(\PHPSchemaManager\Objects\Table $table) {
    
    // describe the tables from the database
    $sql = "DESC $table";
    $resCol = $this->dbQuery($sql);

    while($row = mysql_fetch_assoc($resCol)) {
      // create a new column object
      $column = new \PHPSchemaManager\Objects\Column($row['Field']);
      
      $mysqlColumn = new DriverMysqlColumn($column);
      
      // to get the type we have to first separate the type name from its size, if any.
      $matches = '';
      preg_match("/([a-zA-Z]+)(\(?([']?[0-9a-zA-Z_]*[']?([,]?[']?[0-9a-zA-Z][']?)*)\)?)/", strtolower($row['Type']), $matches);
      
      if (empty($matches[0])) {
        throw new \PHPSchemaManager\Exceptions\MysqlException("Malformed column type {$row['Type']}. Most probably, a not implemented case");
      }

      // if the type is auto_increment let's use the generic type SERIAL
      if ('auto_increment' == strtolower($row['Extra'])) {
        $column->setType(\PHPSchemaManager\Objects\Column::SERIAL);
      }
      else {
        $mysqlColumn->setType($matches[1]);
      }
      
      // set the size of the field, if any
      if (!empty($matches[3])) {
        $size = $matches[3];
        
        // if the type is ENUM or SET, get the biggest value on it
        if ('enum' == strtolower($matches[1]) || 'set' == strtolower($matches[1])) {
          $size = str_replace("'", "", $size);
          $max = 0;
          foreach(explode(",", $size) as $word) {
            if (mb_strlen($word) > $max) {
              $max = mb_strlen($word);
            }
          }
          $size = $max;
        }
        
        $column->setSize($size);
      }
      
      // check if there is the unsigned instruction
      if (!empty($matches[7]) && 'unsigned' == strtolower($matches[7])) {
        $column->unsigned();
      }
      
      // check if the column can receive null values, by default this library assumes it can't
      if ('yes' == strtolower($row['Null'])) {
        $column->allowsNull();
      }
      else {
        $column->forbidsNull();
      }
      
      // set the default value of the column
      $column->setDefaultValue($row['Default']);
      
      // add the column in the table
      $table->addColumn($column);
    }
  }
  
  public function getIndexes(\PHPSchemaManager\Objects\Table $table) {
    //TODO find another way to get the indexes from mysql tables, to support older versions of MySQL
    try {
      // stores the current database, so its can be selected again at the end of this method
      $currentSelectedDb = $this->getDatabaseSelected();
      
      // get the index info from the information_schema database
      $this->selectDb('information_schema');
    }
    catch(\Exception $e) {
      $msg = "While trying to get the indexes for table '$table', the information_schema database wasn't found. Most probably because your MySQL is older than version 5.1";
      throw new \PHPSchemaManager\Exceptions\MysqlException($msg);
    }
    
    // select the indexes (non-primary) created for the database
    $sql = "SELECT * FROM STATISTICS
            WHERE TABLE_SCHEMA = '$currentSelectedDb' AND
            TABLE_NAME = '{$table}'
            ORDER BY SEQ_IN_INDEX ASC";
            
    $res = $this->dbQuery($sql);
    
    $indexes = array();
    while ($row = mysql_fetch_assoc($res)) {
      $indexes[$row['INDEX_NAME']][] = $row;
    }
    
    foreach($indexes as $indexName => $values) {
      $index = new \PHPSchemaManager\Objects\Index($indexName, \PHPSchemaManager\Objects\Index::STATUSSYNCED);
      foreach($values as $idx) {
        if (!$column = $table->hasColumn($idx['COLUMN_NAME'])) {
          throw new \PHPSchemaManager\Exceptions\MysqlException("Trying to create an index with a non-existent column ({$idx['COLUMN_NAME']})");
        }
        $index->addColumn($column, $idx['SEQ_IN_INDEX']);
        
        $index->setType(\PHPSchemaManager\Objects\Index::REGULAR);
        if ("PRIMARY" == $idx['INDEX_NAME']) {
          $index->setType(\PHPSchemaManager\Objects\Index::PRIMARYKEY);
        }
        elseif ("0" == $idx['NON_UNIQUE']) {
          $index->setType(\PHPSchemaManager\Objects\Index::UNIQUE);
        }
        
      }
      
      // add the index into the table
      $table->addIndex($index);
    }
    
    // select the previous selected database again
    $this->selectDb($currentSelectedDb);
  }
  
  protected function alterTable(\PHPSchemaManager\Objects\Table $table) {
    $this->selectDb();
    $sql = "ALTER TABLE $table" . PHP_EOL;
    $sql .= $this->alterTableColumns($table);
    $sql .= $this->alterTableIndexes($table);

    $this->dbQuery($sql);
  }
  
  protected function alterTableColumns(\PHPSchemaManager\Objects\Table $table) {
    $i = 0;
    $instruction = array();
    $sql = "";
    
    foreach($table->getColumns() as $column) {
      
      if ($column->isSynced()) {
        continue;
      }
      elseif ($column->shouldDelete()) {
        $instruction[$i] = "DROP COLUMN $column" . PHP_EOL;
      }
      else {
        
        if ($column->shouldCreate()) {
          $instruction[$i] = "ADD COLUMN " . PHP_EOL;
        }
        elseif($column->shouldAlter()) {
          $instruction[$i] = "MODIFY COLUMN " . PHP_EOL;
        }
        
        $col = new DriverMysqlColumn($column);
        $instruction[$i] .= $col->getDataDefinition();
      }
    }
    
    if (!empty($instruction)) {
      $sql = implode(", " . PHP_EOL, $instruction);
    }
    
    return $sql;
  }
  
  protected function alterTableIndexes(\PHPSchemaManager\Objects\Table $table) {
    $sql = "";
    
    foreach($table->getIndexes() as $index) {
      
      // Mysql doesn't support index modification. @see http://dev.mysql.com/doc/refman/5.0/en/alter-table.html
      if ($index->shouldAlter()) {
        // so we first remove the index...
        $this->dbQuery("DROP INDEX $index ON $table");
        
        // ... and them mark the index to be recreated
        $index->markForCreation();
      }
      
      // if it is SYNCED, do nothing
      if ($index->isSynced()) {
        continue;
      }
      
      // check if the index should be deleted
      elseif ($index->shouldDelete()) {
        $sql .= "DROP INDEX $index" . PHP_EOL;
        $index->markAsDeleted();
        $index->destroy();
      }
      
      // check if the index should be created
      elseif ($index->shouldCreate()) {
        $columns = array();
        foreach($index->getColumns() as $column) {
          $columns[] = "$column";
        }
        $columnsString = implode(", ", $columns);
        
        // check if it is a unique key
        $unique = $index->isUniqueKey() ? "UNIQUE " : "";
        
        $sql .= "ADD {$unique}INDEX $index($columnsString)" . PHP_EOL;
      }

    }
    
    return $sql;
  }
  
  protected function deleteTable(\PHPSchemaManager\Objects\Table $table) {
    $this->selectDb();
    $sql = "DROP TABLE $table";
    $this->dbQuery($sql);
    $table->markAsDeleted();
    $table->destroy();
  }
  
  protected function createTable(\PHPSchemaManager\Objects\Table $table) {
    $this->selectDb();
    $sql = "CREATE TABLE $table (" . PHP_EOL;
    
    foreach($table->getColumns() as $column) {
      $col = new DriverMysqlColumn($column);
      $sql .= $col->getDataDefinition() . "," . PHP_EOL;
    }
    
    // removes the last comma + EOL from the clause. SQL is not like PHP...
    $sql = substr($sql, 0, -3);
    
    $sql .= PHP_EOL . ")";
    
    $this->dbQuery($sql);
  }

  /**
   * Create database
   * 
   * @param string $dbName
   */
  protected function createDatabase($dbName) {
    $sql = "CREATE DATABASE $dbName";
    try {
      $this->dbQuery($sql);
    }catch(\PHPSchemaManager\Exceptions\MysqlException $e) {
      //do nothing, if the database is already created, no problem
    }
  }
  
  protected function dropDatabase(\PHPSchemaManager\Objects\Schema $schema) {
    $sql = "DROP DATABASE $schema";
    $this->dbQuery($sql);
    $schema->markAsDeleted();
    $schema->destroy();
  }


  protected function checkLowerCaseTableNames() {
    //http://dev.mysql.com/doc/refman/5.0/en/identifier-case-sensitivity.html
    $sql = "SHOW VARIABLES LIKE 'lower_case_table_names'";
    
    $res = $this->dbQuery($sql);
    $row = mysql_fetch_assoc($res);
    
    //if 1 or 2, turnCaseSensitiveNamesOff otherwise, turnCaseSensitiveNamesOn
    if (0 === $row['Variable_name']) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }
  
}