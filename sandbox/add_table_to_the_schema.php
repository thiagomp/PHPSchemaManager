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

// List the tables from the schema before adding a new table to the schema
echo "Databases found before adding a new table" . PHP_EOL;
echo $manager->printTxt();

$newTable = new \PHPSchemaManager\Objects\Table('book');

$newColumn = new \PHPSchemaManager\Objects\Column('id');
$newColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
$newColumn->setSize(10);

$newTable->addColumn($newColumn);

$anotherColumn = new \PHPSchemaManager\Objects\Column('language');

// this time we are creating a Column of CHAR type
$anotherColumn->setType(\PHPSchemaManager\Objects\Column::CHAR);
$anotherColumn->setSize(2);

// this is also new. We are defining the default value for this Column
$anotherColumn->setDefaultValue("EN");

// now add the Column to the Table object
$newTable->addColumn($anotherColumn);


// add a title column
$titleColumn = new \PHPSchemaManager\Objects\Column('title');
$titleColumn->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
$titleColumn->setSize(250);

// this column will not allow NULL as a valid value
$titleColumn->forbidsNull();

// add the title column to the table object
$newTable->addColumn($titleColumn);


// add a ISBN column to the table
$isbnColumn = new \PHPSchemaManager\Objects\Column('isbn');
$isbnColumn->setType(\PHPSchemaManager\Objects\Column::CHAR);
$isbnColumn->setSize(13);

$newTable->addColumn($isbnColumn);

// Once the Table creation is done, its time to add the table to the Schema
// The TRUE parameter will drop the table and create with the new definitions,
// in case the table already exists
$schema->addTable($newTable, TRUE);

// Prints the table data
echo $newTable->printTxt();

// Persist the data into the database
$manager->flush();

// Prints the table data after the flush
echo $newTable->printTxt();

// List the tables from the schema after adding a new table to the schema
echo "Databases found after adding a new table" . PHP_EOL;
echo $manager->printTxt();