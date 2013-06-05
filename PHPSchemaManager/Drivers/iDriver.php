<?php
namespace PHPSchemaManager\Drivers;

interface Driver {
  
    public function connect();
    public function selectDb();
    public function getSchemas();
    public function getTables(\PHPSchemaManager\Objects\Schema $schema);
    public function dbQuery($sql);
    public function dbFetchArray($result);
    public function getCreateTableStatement(\PHPSchemaManager\Objects\Table $table);
    public function getVersion();

    /**
     * Retrieves how many rows there is in a table
     * 
     * @param string $table
     */
    public function getTableCount($table);


    public function flush(\PHPSchemaManager\Objects\Schema $schema);

    /**
     * Checks if the database works with name in lower case only or if it's 
     * case sensitive
     * 
     * @return Boolean TRUE if working in lower case mode, FALSE if is case sensitive
     */
    public function checkLowerCaseTableNames();
}
