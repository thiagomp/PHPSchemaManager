<?php
require("../PHPSchemaManager/PHPSchemaManager.php");

\PHPSchemaManager\PHPSchemaManager::registerAutoload();

$connection = new \PHPSchemaManager\Connection();
$connection->dbms = 'mysql';
$connection->username = 'root';
$connection->password = '';
$connection->hostname = '127.0.0.1';
$connection->port = '3306';

$manager = \PHPSchemaManager\PHPSchemaManager::getManager($connection);

echo $manager->hasSchema('test')->printTxt();