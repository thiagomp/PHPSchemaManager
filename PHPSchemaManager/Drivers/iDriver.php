<?php
namespace PHPSchemaManager\Drivers;

interface iDriver {
  
  function connect();
  function selectDb();
  function getSchemas();
  function getTables(\PHPSchemaManager\Objects\Schema $schema);
  function dbQuery($sql);
  function dbFetchArray($result);
  function getCreateTableStatement(\PHPSchemaManager\Objects\Table $table);
  
  function setIgnoredSchemas(Array $schemaNames);
  function setExclusiveSchema($schemaName);
  
  /**
   * Retrieves how many rows there is in a table
   * 
   * @param string $table
   */
  function getTableCount($table);

  
  function flush(\PHPSchemaManager\Objects\Schema $schema);
}