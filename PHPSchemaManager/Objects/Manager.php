<?php
namespace PHPSchemaManager\Objects;

class Manager
  extends Objects 
  implements iFather {
  
  protected $ignoreSchemas = array();
  
  const DEFULTCONNECTION = 'default';
  
  function __construct(\PHPSchemaManager\Connection $defaultConnection) {
    $this->addConnection($defaultConnection, self::DEFULTCONNECTION);;
  }
  
  /**
   * Add a new connection to the SchemaManager
   * 
   * @param \PHPSchemaManager\Connection $connection
   * @param string $connectionName
   */
  public function addConnection(\PHPSchemaManager\Connection $connection, $connectionName) {
    $connection->connectionName = $connectionName;
    $this->connections[$connectionName] = $connection;
    $this->connect($connectionName);
  }
  
  /**
   * Recovers a connection by its name
   * 
   * @param string $connectionName
   * @return \PHPSchemaManager\Connection|Boolean
   */
  public function getConnection($connectionName) {
    return empty($this->connections[$connectionName]) ? FALSE : $this->connections[$connectionName];
  }
  
  /**
   * Return the database connection. Once connected, the same connection will
   * be always used
   * 
   * @return \PHPSchemaManager\Drivers\iDriver
   */
  public function connect($connectionName = self::DEFULTCONNECTION) {
    $conn = $this->getConnection($connectionName);
    if (empty($conn->handle)) {
      // get the database driver that will be used
      $conn->driver = \PHPSchemaManager\Drivers\Driver::getDbms($conn);
      
      // stabilishes a connection
      $conn->driver->connect();

      //gets all schemas found in this connection
      foreach ($conn->driver->getSchemas(self::getIgnoredSchemas()) as $schema) {
        $this->addSchema($schema, $connectionName);
        $schema->setFather($this);
      }
    }
  }
  
  /**
   * Persists all changes done in the Table, Column or Index objects to the database
   */
  public function flush() {
    if (!empty($this->schemas)) {
      foreach($this->schemas as $connectionName => $schemas){
        $conn = $this->getConnection($connectionName);
        foreach($schemas as $schema) {
          $conn->driver->flush($schema);
        }
      }
    }
  }
  
  /**
   * Add a new Schema in to the SchemaManager
   * 
   * @param \PHPSchemamanager\Objects\Schema $schema
   * @param string $connectionName
   * @return Boolean TRUE if the schema could be added or FALSE if the schema couldn't be added
   */
  public function addSchema(Schema $schema, $connectionName = NULL) {
    
    if (empty($connectionName)) {
      $connectionName = self::DEFULTCONNECTION;
    }
    
    try{
      $schema->setConnection($this->getConnection($connectionName));
      $schemaName = $schema->isCaseSensitiveNamesOn() ? $schema->getSchemaName() : strtolower($schema->getSchemaName());
      
      // add the schema for this manager
      $this->schemas[$connectionName][$schemaName] = $schema;
      
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
   * @param string $connectionName
   * @return \PHPSchemaManager\Objects\Schema|Boolean
   */
  public function hasSchema($schemaName, $connectionName = NULL) {
    
    // normalizes the connection name
    if (empty($connectionName)) {
      $connectionName = self::DEFULTCONNECTION;
    }

    // check if the desired schema exists in the selected connection
    if (!empty($this->schemas) && !empty($this->schemas[$connectionName])) {

      // try to get the schema from the class
      foreach($this->schemas[$connectionName] as $currentSchemaName => $schema) {
        
        /* @var $schema \SchemaManager\Objects\Schema*/
        if ($currentSchemaName == $schemaName || (!$schema->isCaseSensitiveNamesOn() && strtolower($currentSchemaName) == strtolower($schemaName))) {
          // schema is found, but first checks if it's marked to be deleted
          return $schema->shouldDelete() || $schema->isDeleted() ? FALSE : $schema;
        }
        
      }
    }

    // the schema wasn't found in the informed connection
    return FALSE;
  }
  
  /**
   * Return an array of Schemas
   * In case you send NULL for the parameter, all schemas for all connections
   * will be retrieved
   * 
   * @param string $connectionName
   * @return \PHPSchemaManager\Objects\Schema[]
   */
  public function getSchemas($connectionName = self::DEFULTCONNECTION) {
    return empty($connectionName) ? $this->schemas : $this->schemas[$connectionName];
  }
  
  public function dropSchema($schemaName, $connectionName = self::DEFULTCONNECTION) {
    
    /* @var $schema \PHPSchemaManager\Objects\Schema */
    $schema = $this->hasSchema($schemaName);
    
    if (FALSE !== $schema) {
      $schema->markForDeletion();
    }
    else {
      $msg = "Schema '$schemaName' couldn't be dropped since it wasn't found in the connection '$connectionName'";
      throw new \PHPSchemaManager\Exceptions\SchemaException($msg);
    }
  }
  
  public function loadFromJSONFile($filePath) {
    // check if the file can be read
    if (!is_readable($filePath)) {
      $msg = "File '$filePath' can't be opened. Check if the file exists and its permissions";
      throw new \SchemaManager\Exceptions\FileException($msg);
    }
    
    // try to decode the JSON format
    if (!$json = json_decode(file_get_contents($filePath), true)) {
      
      $msg = "An error ocurred while processing the JSON file: ";
      
      switch (json_last_error()) {
        case JSON_ERROR_NONE:
          $msg .= 'No errors';
          break;
        case JSON_ERROR_DEPTH:
          $msg .= 'Maximum stack depth exceeded';
          break;
        case JSON_ERROR_STATE_MISMATCH:
          $msg .= 'Underflow or the modes mismatch';
          break;
        case JSON_ERROR_CTRL_CHAR:
          $msg .= 'Unexpected control character found';
          break;
        case JSON_ERROR_SYNTAX:
          $msg .= 'Syntax error, malformed JSON';
          break;
        case JSON_ERROR_UTF8:
          $msg .= 'Malformed UTF-8 characters, possibly incorrectly encoded';
          break;
        default:
          $msg .= 'Unknown error';
      }
      
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
      
      if (empty($items['columns'])) {
        $msg = "Columns definitions wasn't found in the JSON file";
        throw new \PHPSchemaManager\Exceptions\ManagerException($msg);
      }
      
      // get all the columns from the current table
      foreach($items['columns'] as $columnName => $columnDefinitions) {
        $column = new Column($columnName);
        
        foreach($columnDefinitions as $action => $definition) {
          switch($action) {
            case 'type':
              $column->setType($definition);
              break;
            case 'size':
              $column->setSize($definition);
              break;
            case 'null allowed':
              strtolower($definition) == 'yes' ? $column->allowsNull() : $column->forbidsNull();
              break;
            case 'default value';
              $column->setDefaultValue($definition);
              break;
            default:
              $msg = "Action '$action' for column wasn't reconized while loading data from JSON file";
              throw new \PHPSchemaManager\Exceptions\ManagerException();
          }
        }
        
        $table->addColumn($column);
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
    foreach($this->schemas as $connectionName => $currentSchemas) {
      if (array_key_exists($schema->getSchemaName(), $currentSchemas))
        unset($this->schemas[$connectionName][$schema->getSchemaName()]);
        return TRUE;
      }
    
    throw new \PHPSchemaManager\Exceptions\SchemaException("Schema '$schema' couldn't be dropped from the connection {$this->conn->connectionName}");
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
  public function setIgnoreSchemas($schemaNames) {
    $this->ignoreSchemas = $schemaNames;
  }

  public function getIgnoredSchemas() {
    return $this->ignoreSchemas;
  }
}