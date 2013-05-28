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

// Configure the schemas that will be ignored by the library
$manager->setIgnoredSchemas(array('information_schema', 'performance_schema', 'mysql', 'test'));

// Print the schemas
echo $manager->printTxt();