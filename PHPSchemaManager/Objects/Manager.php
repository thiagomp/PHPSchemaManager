<?php
namespace PHPSchemaManager\Objects;

class Manager
  extends Objects 
  implements iFather {
  
  protected $ignoredSchemas = array();
  protected $exclusiveSchema = FALSE;
  protected $schemas = array();
  protected $firstFetchExecuted = FALSE;
  protected $connection;
  protected $driver;
  protected $goToDatabase = TRUE;
  protected $fetchAllowed = TRUE;

  const DEFULTCONNECTION = 'default';
  
  function __construct(\PHPSchemaManager\Connection $conn) {
    $this->setConnection($conn);
  }
  
  /**
   * Add a new connection to the Manager
   * 
   * @param \PHPSchemaManager\Connection $connection
   */
  public function setConnection(\PHPSchemaManager\Connection $connection) {
    $this->connection = $connection;
    $this->connect();
  }
  
  /**
   * Recovers a connection by its name
   * 
   * @return \PHPSchemaManager\Connection|Boolean
   */
  public function getConnection() {
    return $this->connection;
  }
  
  /**
   * Return the database connection. Once connected, the same connection will
   * be always used
   * 
   * @return \PHPSchemaManager\Drivers\iDriver
   */
  public function connect() {
    $conn = $this->getConnection();
    
    // get the database driver that will be used
    $conn->driver = \PHPSchemaManager\Drivers\Driver::getDbms($conn);

    // stabilishes a connection
    $conn->driver->connect();
    $conn->driver->checkLowerCaseTableNames();
  }
  
  /**
   * Persists all changes done in the Table, Column or Index objects to the database
   */
  public function flush() {
    if (!empty($this->schemas)) {
      $conn = $this->getConnection();
      foreach($this->schemas as $schema){
        $conn->driver->flush($schema);
      }
    }
  }
  
  /**
   * Add a new Schema in to the SchemaManager
   * 
   * @param \PHPSchemamanager\Objects\Schema $schema
   * @return Boolean TRUE if the schema could be added or FALSE if the schema couldn't be added
   */
  public function addSchema(Schema $schema) {
    $this->fetchFromDatabase();
    
    try{
      $schemaName = strtolower($schema->getName());
      // check if there's a exclusive schema...
      if ($exclusiveSchema = $this->getExclusiveSchema()) {
        // if yes, mark all other schemas to be ignored
        if (!$schema->nameCompare($exclusiveSchema)) {
          $schema->ignore();
        }
      }
      
      // check if there's a list of schemas to be ignored
      if ($ignoredSchemas = $this->getIgnoredSchemas()) {
        foreach ($ignoredSchemas as $ignoredSchemaName) {
          if ($schema->nameCompare($ignoredSchemaName)) {
            $schema->ignore();
          }
        }
      }
      
      $schema->setFather($this);
      
      // add the schema for this manager
      $this->schemas[$schemaName] = $schema;
      
      return TRUE;
    }
    catch(Exception $e) {
      return FALSE;
    }
  }
  
  /**
   * Searches for a schema by its name or alias.
   * In case a connection is ommited, the method will use the defaultConnection
   * configured in the instaciation of the object
   * 
   * @param string $schemaName
   * @return \PHPSchemaManager\Objects\Schema|Boolean
   */
  public function hasSchema($schemaName) {
    
    $this->fetchFromDatabase();
    
    // try to get the schema from the class
    foreach($this->schemas as $currentSchemaName => $schema) {

      /* @var $schema \PHPSchemaManager\Objects\Schema */
      
      if ($schema->nameCompare($schemaName)) {
        
        if ($this->shouldIgnoreDeleted()) {
          return $schema;
        }
        
        // schema is found, but first checks if it's marked to be deleted
        return $schema->shouldDelete() || $schema->isDeleted() ? FALSE : $schema;
      }

    }

    // the schema wasn't found in the informed connection
    return FALSE;
  }
  
  /**
   * Return an array of Schemas that belongs to the connection of this manager
   * 
   * @return \PHPSchemaManager\Objects\Schema[]
   */
  public function getSchemas() {
    //return empty($this->schemas) ? $this->schemas : $this->schemas;
    return $this->schemas;
  }
  
  public function dropSchema($schemaName) {
    
    /* @var $schema \PHPSchemaManager\Objects\Schema */
    $schema = $this->hasSchema($schemaName);
    
    if (FALSE !== $schema) {
      $schema->drop();
    }
    else {
      $msg = "Schema '$schemaName' couldn't be dropped since it wasn't found in the currnet connection";
      throw new \PHPSchemaManager\Exceptions\SchemaException($msg);
    }
  }
  
  /**
   * Creates the PHPSchemaManager objects based on a JSON string
   * By the end of the process, this Manager object will populated with the
   * objects
   * Notice that this method expects the file to have one schema inside
   * 
   * @param type $filePath
   * @throws \PHPSchemaManager\Exceptions\FileException
   * @throws \PHPSchemaManager\Exceptions\FileException
   * @throws \PHPSchemaManager\Exceptions\ManagerException
   */
  public function loadFromJSONFile($filePath) {
    // check if the file can be read
    if (!is_readable($filePath)) {
      $msg = "File '$filePath' can't be opened. Check if the file exists and its permissions";
      throw new \PHPSchemaManager\Exceptions\FileException($msg);
    }
    
    $this->loadFromJSONString(file_get_contents($filePath));
  }
  
  /**
   * Creates the PHPSchemaManager objects based on a JSON string
   * By the end of the process, this Manager object will populated with the
   * objects
   * Notice that this method expects the file to have one schema inside
   * 
   * @param type $jsonString
   * @throws \PHPSchemaManager\Exceptions\FileException
   * @throws \PHPSchemaManager\Exceptions\ManagerException
   */
  public function loadFromJSONString($jsonString) {
    // try to decode the JSON format
    if (!$json = json_decode($jsonString, true)) {
      
      $msg = "An error ocurred while processing the JSON file: ";
      $msg .= $this->getJSONErrorMessage(json_last_error());
      
      throw new \PHPSchemaManager\Exceptions\FileException($msg);
    }
    
    // get the schema name from the json file
    $schemaName = key($json);
    
    // check if the schema already exists
    if ($this->hasSchema($schemaName)) {
      $msg = "Schema '$schemaName' already exists in this database. Remove it first before importing the data";
      throw new \PHPSchemaManager\Exceptions\ManagerException($msg);
    }
    
    $schema = new Schema($schemaName);
    
    // get all the tables from the current schema
    foreach($json[$schemaName] as $tableName => $items) {
      $table = new Table($tableName);
      
      // get all the columns from the current table
      $this->getColumnsFromJSON($table, $items);
      
      // get all the indexes from the current table
      $this->getIndexesFromJSON($table, $items);
      
      $schema->addTable($table);
    }
    
    $this->addSchema($schema);
  }
  
  ## Compliying with iFather ##
    public function informChange() {
    // do nothing
  }
  
  public function informDeletion(Objects $object) {
    if ($object instanceof Schema) {
      $this->removeSchema($object);
    }
  }
  ## End of the iFather methods
  
  protected function removeSchema(Schema $schema) {
    $this->ignoreDeleted();
    if ($this->hasSchema($schema->getName())) {
      $this->regardDeleted();
      unset($this->schemas[$schema->getName()]);
      return TRUE;
    }
    
    throw new \PHPSchemaManager\Exceptions\SchemaException("Schema '$schema' couldn't be dropped from the current connection");
  }
  
  /**
   * Configure which schemas should be ignored
   * You should inform a Array of strings with the name of the ignored schemas
   * The ignored schemas, will not be populated with its tables
   * The schemas objects will be created only to avoid schemas with the same
   * name to be created
   * 
   * @param Array $schemaNames
   */
  public function setIgnoredSchemas($schemaNames) {
    $this->ignoredSchemas = $schemaNames;
  }

  public function getIgnoredSchemas() {
    return empty($this->ignoredSchemas) ? FALSE: $this->ignoredSchemas;
  }
  
  /**
   * Inform which schema will be used exclusively by the library.
   * Once this is informed, all the details of the other schemas will be
   * ignored. This configuration can be good for performance, in case you have
   * lots of schemas in the connection
   * 
   * @param type $schemaName
   */
  public function setExclusiveSchema($schemaName) {
    $this->exclusiveSchema = $schemaName;
  }

  public function getExclusiveSchema() {
    return empty($this->exclusiveSchema) ? FALSE : $this->exclusiveSchema;
  }

  public function printTxt() {
    
    $this->fetchFromDatabase();
    
    $conn = $this->getConnection();
    
    $msg = $this->countSchemas() . " schemas were found in the connection '$conn' " .
            "({$conn->username}@{$conn->hostname}:{$conn->port} [{$conn->dbms}])" . PHP_EOL;
    foreach($this->getSchemas() as $schema) {
      $msg .= "$schema ";
      if ($schema->shouldBeIgnored()) {
        $msg .= "(ignored)" . PHP_EOL;
      }
      elseif(0 === $schema->countTables()) {
        $msg .= "(no tables)" . PHP_EOL;
      }
      elseif(1 === $schema->countTables()) {
        $msg .= "(1 table)"  . PHP_EOL;
      }
      else {
        $msg .= "({$schema->countTables()} tables)" . PHP_EOL;
      }
    }
    $msg .= str_repeat("-", 30) . PHP_EOL . PHP_EOL;
    
    return $msg;
  }
  
  public function countSchemas() {
    $this->fetchFromDatabase();
    return count($this->getSchemas());
  }
  
  public function getDatabaseVersion() {
    $conn = $this->getConnection();
    return $conn->driver->getVersion();
  }
  
  /**
   * Go to the database and fetch information from the schemas
   * This method will be called only if the schemas are empty
   */
  protected function fetchFromDatabase() {
    
    if (!$this->isFetchAllowed()) {
      return FALSE;
    }
    
    if ($this->goToDatabase) {
      $conn = $this->getConnection();
      
      //gets all schemas found in this connection
      foreach ($conn->driver->getSchemas() as $schema) {
        /* @var $schema \PHPSchemaManager\Objects\Schema */
        $this->forbidFetch();
        $this->addSchema($schema);
        $this->allowFetch();
        $schema->setFather($this);
        
        if (!$schema->shouldBeIgnored()) {
          $conn->driver->getTables($schema);
        }
      }
      
      $this->goToDatabase = FALSE;
    }
  }
  
  /**
   * Get the error id informed by the json_last_error() function and returns
   * the appropriate error message
   * 
   * @param type $errorId
   * @return string
   */
  protected function getJSONErrorMessage($errorId) {
      switch ($errorId) {
        case JSON_ERROR_NONE:
          $msg = 'No errors';
          break;
        case JSON_ERROR_DEPTH:
          $msg = 'Maximum stack depth exceeded';
          break;
        case JSON_ERROR_STATE_MISMATCH:
          $msg = 'Underflow or the modes mismatch';
          break;
        case JSON_ERROR_CTRL_CHAR:
          $msg = 'Unexpected control character found';
          break;
        case JSON_ERROR_SYNTAX:
          $msg = 'Syntax error, malformed JSON';
          break;
        case JSON_ERROR_UTF8:
          $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
          break;
        default:
          $msg = 'Unknown error';
      }
      
      return $msg;
  }
  
  protected function getColumnsFromJSON(Table $table, $items) {
    
    if (empty($items['columns'])) {
      $msg = "Columns definitions wasn't found in the JSON file";
      throw new \PHPSchemaManager\Exceptions\ManagerException($msg);
    }
    
    // get all the columns from the current table
    foreach($items['columns'] as $columnName => $columnDefinitions) {

      $column = new Column($columnName);
      
      foreach($columnDefinitions as $action => $definition) {
        $action = strtolower($action);
        switch($action) {
          case 'type':
            $column->setType($definition);
            break;
          case 'size':
            $column->setSize($definition);
            break;
          case 'allownull':
            strtolower($definition) == 'yes' ? $column->allowsNull() : $column->forbidsNull();
            break;
          case 'defaultvalue';
            $definition = empty($definition) ? NULL : $definition;
            $column->setDefaultValue($definition);
            break;
          default:
            $msg = "Action '$action' for column wasn't reconized while loading data from JSON file";
            throw new \PHPSchemaManager\Exceptions\ManagerException($msg);
        }
      }
    
      $table->addColumn($column);
    }
    
  }
  
  protected function getIndexesFromJSON(Table $table, $items) {
    
    if (empty($items['keys'])) {
      $msg = "Columns definitions wasn't found in the JSON file";
      throw new \PHPSchemaManager\Exceptions\ManagerException($msg);
    }
    
    // get all the indexes from the current table
    foreach($items['keys'] as $indexName => $indexDefinitions) {
      
      $index = new Index($indexName);

      foreach($indexDefinitions as $action => $definition) {
        switch ($action) {
          case 'type':
            $index->setType($definition);
            break;
          case 'columns':
            foreach($definition as $columnName) {
              $index->addColumn($table->hasColumn($columnName));
            }
            break;
          default:
            $msg = "Action '$action' for index wasn't reconized while loading data from JSON file";
            throw new \PHPSchemaManager\Exceptions\ManagerException();
        }
      }
    
      $table->addIndex($index);
    }

  }
  
  protected function forbidFetch() {
    $this->fetchAllowed = FALSE;
  }
  
  protected function allowFetch() {
    $this->fetchAllowed = TRUE;
  }
  
  protected function isFetchAllowed() {
    return $this->fetchAllowed;
  }
  
}
