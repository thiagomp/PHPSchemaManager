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

// List the schemas found
echo "Databases found before adding a new one" . PHP_EOL;
echo $manager->printTxt();

// Create a new schema called PHPSchemaManager
$schema = new \PHPSchemaManager\Objects\Schema("PHPSchemaManager");

// Add the creatd schema to the manager
$manager->addSchema($schema);

// List the schemas again. We expect to see the newly added schema
echo "Databases found after adding a new one" . PHP_EOL;
echo $manager->printTxt();

// Asks the manager to persist the changes into the database
$schema->flush();