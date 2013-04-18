<?php
require('PHPSchemaManager/PHPSchemaManager.php');

\PHPSchemaManager\PHPSchemaManager::registerAutoload();

$conn = new \PHPSchemaManager\Connection();
$conn->dbms = 'mysql';
$conn->username = 'root';
$conn->password = '';
$conn->hostname = '127.0.0.1';

$sm = \PHPSchemaManager\PHPSchemaManager::getManager($conn);
$sm->setIgnoreSchemas(array('information_schema', 'performance_schema', 'mysql', 'test'));

if ($s = $sm->hasSchema('PHPSchemaManagerTest')) {
  // make sure that none of the tables that will be used in the test exists
  if ($s->hasTable("book")) {
    $s->dropTable("book");
    echo "INFO: Book table dropped" . PHP_EOL;
  }

  if ($s->hasTable("wrongTable")) {
    $s->dropTable("wrongTable");
    echo "INFO: wrongTable table dropped" . PHP_EOL;
  }
  
  echo "INFO: schema $s has " . $s->countTables() . " tables" . PHP_EOL;
}
 
if ($s = $sm->hasSchema('Library')) {
  $sm->dropSchema("Library");
  
  echo "INFO: schema 'Library' have been removed" . PHP_EOL;
}

$sm->flush();