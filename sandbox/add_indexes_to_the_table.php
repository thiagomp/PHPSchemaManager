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

// Check if the table exists in the database
// if not, show an error
if (!$bookTable = $schema->hasTable('book')) {
  echo "In order to execute this example, the table 'book' must exist.\n";
  echo "You can create it executing the add_table_to_the_schema.php script.";
  exit(1);
}

// Prints the table to see the state of the indexes now
echo "State of the Indexes now:\n";
echo $bookTable->printTxt();

// Create a new Index object
$titleIdx = new \PHPSchemaManager\Objects\Index('titleIdx');

// Add a Column object to the Index object
$titleIdx->addColumn($bookTable->hasColumn('title'));

// Add the Index object to the Table object
$bookTable->addIndex($titleIdx);

// Prints the table to see the recently created Index
echo "Notice the created index 'titleIdx'\n";
echo $bookTable->printTxt();


// Creates a Index that is a Unique Key
$isbnIdx = new \PHPSchemaManager\Objects\Index('isbnIdx');
$isbnIdx->addColumn($bookTable->hasColumn('isbn'));
$isbnIdx->setAsUniqueKey();
$bookTable->addIndex($isbnIdx);

// Prints the table to see the Unique Key Index created
echo "Notice the created Unique Key index 'isbnIdx'\n";
echo $bookTable->printTxt();


// Create a Index that will have multiple Columns
$multIdx = new \PHPSchemaManager\Objects\Index('multIdx');
$multIdx->addColumn($bookTable->hasColumn('title'));
$multIdx->addColumn($bookTable->hasColumn('language'));
$bookTable->addIndex($multIdx);

// Prints the table to see the Multiple Key Index created
echo "Notice the Multiple Key Index created 'multIdx'\n";
echo $bookTable->printTxt();

// Commits the changes to the database
$schema->flush();

// Prints the table to see the Multiple key Index created
echo "Now see the state of the Indexes after the flush\n";
echo $bookTable->printTxt();