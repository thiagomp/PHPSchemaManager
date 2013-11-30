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

// check if the tables used in this example are already created
if ($schema->hasTable('order')) {
    $schema->hasTable('order')->drop();
    $schema->flush();
}
if ($schema->hasTable('customer')) {
    $schema->hasTable('customer')->drop();
    $schema->flush();
}
if ($schema->hasTable('tire')) {
    $schema->hasTable('tire')->drop();
    $schema->flush();
}
if ($schema->hasTable('supplier')) {
    $schema->hasTable('supplier')->drop();
    $schema->flush();
}

// Creates a supplier table
$supplierId = new \PHPSchemaManager\Objects\Column('id');
$supplierId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

$supplierName = new \PHPSchemaManager\Objects\Column('name');
$supplierName->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
$supplierName->setSize(100);

$supplierTable = new \PHPSchemaManager\Objects\Table('supplier');
$supplierTable->addColumn($supplierId);
$supplierTable->addColumn($supplierName);

$specifics = new \PHPSchemaManager\Drivers\TableSpecificMysql();
$specifics->markAsInnoDb();

$supplierTable->addSpecificConfiguration($specifics);

// Prints the supplier table
echo "Supplier table created:" . PHP_EOL;
echo $supplierTable->printTxt();

// Creates the id field for the tire
$tireId = new \PHPSchemaManager\Objects\Column('id');
$tireId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

// Adds a supplier field in the tire table
$tireSupplierId = new \PHPSchemaManager\Objects\Column('supplierId');
$tireSupplierId->references($supplierId);


$tireTable = new \PHPSchemaManager\Objects\Table('tire');
$tireTable->addColumn($tireId);
$tireTable->addColumn($tireSupplierId);
$tireTable->addSpecificConfiguration($specifics);


// Shows tire table with the FK
echo "Tire table created with FK:" . PHP_EOL;
echo $tireTable->printTxt();

// Creates a customer table
$customerId = new \PHPSchemaManager\Objects\Column('id');
$customerId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

$customerTable = new \PHPSchemaManager\Objects\Table('customer');
$customerTable->addColumn($customerId);
$customerTable->addSpecificConfiguration($specifics);

// Prints customer table
echo "Customer table created: " . PHP_EOL;
echo $customerTable->printTxt();

// Creates an order table and create the FKs there
$orderNo = new \PHPSchemaManager\Objects\Column('id');
$orderNo->setType(\PHPSchemaManager\Objects\Column::SERIAL);

// Create reference to the Tire table
// This time, the carbon copy is used to create a exact copy of the column that will be referenced
// The actionOnDelete method will be called empty, which means that on delete, no action will be taken
$orderTireId = $tireId->carbonCopy('tireId');
$orderTireId->references($tireId)->actionOnDelete();

// Create a reference to the customer table
// Below is showed how to explicitly inform the NO ACTION On Delete.
$orderCustomerId = new \PHPSchemaManager\Objects\Column('customerId');
$orderCustomerId->references($customerId)->actionOnDelete(\PHPSchemaManager\Objects\ColumnReference::NOACTION);

$orderTable = new \PHPSchemaManager\Objects\Table('order');
$orderTable->addColumn($orderNo);
$orderTable->addColumn($orderTireId);
$orderTable->addColumn($orderCustomerId);
$orderTable->addSpecificConfiguration($specifics);

// Show the order table with its references
echo "Order table created with multiple FKs on it. Notice that the action On Delete is NO ACTION " . PHP_EOL;
echo $orderTable->printTxt();

// add the tables to the schema
$schema->addTable($supplierTable);
$schema->addTable($tireTable);
$schema->addTable($customerTable);
$schema->addTable($orderTable);


// Commits the changes to the database
$schema->flush();

// Prints the table to see the FKs persisted in the database
echo "Now see the FK created in the Order table, after the flush to the database\n";
echo $orderTable->printTxt();