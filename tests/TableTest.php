<?php
require_once('PHPUnit/Autoload.php');

/**
 * Description of TableTest
 *
 * @author thiago
 */
class TableTest
  extends PHPUnit_Framework_TestCase {
  
  public function testTableCreationStatus() {
    $newTable = new \PHPSchemaManager\Objects\Table('book');
    $this->assertTrue($newTable->shouldCreate());
  }
  
  public function testSerialTypeCreatesIndexAutomatically() {
    $myTestTable = new \PHPSchemaManager\Objects\Table('myTest');
    
    $myTestIdColumn = new \PHPSchemaManager\Objects\Column('id');
    $myTestIdColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $myTestIdColumn->setSize(10);
    
    $myTestTable->addColumn($myTestIdColumn);
    
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index',
            $myTestTable->hasIndex('PRIMARY'),
            "Check if the 'PRIMARY' link was automatically created");
    
    $this->assertTrue($myTestTable->hasIndex('PRIMARY')->isPrimaryKey(), "Check if the created key is a primary key");
  }
}
