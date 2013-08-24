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

// Creates an author table
$authorId = new \PHPSchemaManager\Objects\Column('id');
$authorId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

$authorName = new \PHPSchemaManager\Objects\Column('name');
$authorName->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
$authorName->setSize(100);

$authorTable = new \PHPSchemaManager\Objects\Table('author');
$authorTable->addColumn($authorId);
$authorTable->addColumn($authorName);

// Prints the author table
echo "Author table created" . PHP_EOL;
$authorTable->printTxt();

// Check if the authorId column exists in the book table
if ($bookTable->hasColumn('authorId')) {
    // if exists, just drop it, because it will be easier to understand the example about creating a new reference
    $bookTable->hasColumn('authorId')->drop();
}

// Adds an author field in the book table
$bookAuthorId = new \PHPSchemaManager\Objects\Column('authorId');
$bookAuthorId->setType(\PHPSchemaManager\Objects\Column::INT);
$bookAuthorId->references($authorId);
$bookTable->addColumn($bookAuthorId);

// Shows book table with the FK
echo $bookTable->printTxt();

// Persist the changes to the book table
$schema->flush();
/*
// Creates a customer table
$customerId = new \PHPSchemaManager\Objects\Column('id');
$customerId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

$customerTable = new \PHPSchemaManager\Objects\Table('customer');
$customerTable->addColumn($customerId);

// Prints customer table
echo "Customer table created" . PHP_EOL;
$customerTable->printTxt();

// Creates an order table and create the FKs there
$orderNo = new \PHPSchemaManager\Objects\Column('id');
$orderNo->setType(\PHPSchemaManager\Objects\Column::SERIAL);

// Create references to the Book table
$orderBookId = new \PHPSchemaManager\Objects\Column('bookId');
$orderBookId->references($bookTable->hasColumn('id'));
$orderAuthorId = new \PHPSchemaManager\Objects\Column('authorId');
$orderAuthorId->references($bookTable->hasColumn('authorId'));

// Create a reference to the customer table
$orderCustomerId = new \PHPSchemaManager\Objects\Column('customerId');
$orderCustomerId->references($customerTable->hasColumn('id'));

$orderTable = new \PHPSchemaManager\Objects\Table('order');
$orderTable->addColumn($orderNo);
$orderTable->addColumn($orderBookId);
$orderTable->addColumn($orderAuthorId);
$orderTable->addColumn($orderCustomerId);

// Show the order table with its references
$orderTable->printTxt();

// Commits the changes to the database
$schema->flush();

// Prints the table to see the FKs persisted in the database
echo "Now see the FK created after the flush to the database\n";
echo $orderTable->printTxt();
*/