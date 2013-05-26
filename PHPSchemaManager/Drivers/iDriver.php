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
  function getVersion();
  function checkLowerCaseTableNames();
  
  /**
   * Retrieves how many rows there is in a table
   * 
   * @param string $table
   */
  function getTableCount($table);

  
  function flush(\PHPSchemaManager\Objects\Schema $schema);
  
  /**
   * Checks if the database works with name in lower case only or if it's 
   * case sensitive
   * 
   * @return Boolean TRUE if working in lower case mode, FALSE if is case sensitive
   */
  function checkLowerCaseTableNames();
}
