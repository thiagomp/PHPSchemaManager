<?php
require('../PHPSchemaManager/PHPSchemaManager.php');

// Register the PHPSchemaManager's autoloader:
\PHPSchemaManager\PHPSchemaManager::registerAutoload();

// Get the connection object
$connection = new \PHPSchemaManager\Connection();

// Configure how to connect in the server
$connection->dbms = 'mysql';
$connection->username = 'username';
$connection->password = 'password';
$connection->hostname = '127.0.0.1';
$connection->port = '3306';

// Get the manager instance
$manager = \PHPSchemaManager\PHPSchemaManager::getManager($connection);

// Check if the desired Schema to add the table exists.
// In case it doesn't exists, create it
if (!$schema = $manager->hasSchema('PHPSchemaManager')) {
  $schema = new \PHPSchemaManager\Objects\Schema('PHPSchemaManager');
  $manager->addSchema($schema);
}

// Create a Column
$column = new \PHPSchemaManager\Objects\Column('columnA');
$column->setType(\PHPSchemaManager\Objects\Column::TIMESTAMP);

// Create a Table
$table = new \PHPSchemaManager\Objects\Table('toBeDroppedTable');

// Add the Column to the Table
$table->addColumn($column);

// Add the Table to the Schema
$schema->addTable($table);

// persiste the changes into the database
$schema->flush();

// print the schema to show the table is created in the database and the object is in sync with it.
echo "Schema with the table to be dropped:" . PHP_EOL;
echo $schema->printTxt();

// now, sends the command to drop the table
$schema->dropTable('toBeDroppedTable');

// prints the schema to show the table is already 'dropped' from the object
echo "Show schema tables after the dropTable command and before the flush:" . PHP_EOL;
echo $schema->printTxt();

// now flushes the changes
$schema->flush();

// prints the schema again just to doube check
echo "Show the schema before flushing the changes" . PHP_EOL;
echo $schema->printTxt();