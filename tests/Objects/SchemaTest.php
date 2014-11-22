<?php

class SchemaTest
  extends PHPUnit_Framework_TestCase {
  
  
  /**
   * @expectedException \PHPSchemaManager\Exceptions\SchemaException
   */
  public function testTableWithoutColumn() {
    $schema = new \PHPSchemaManager\Objects\Schema('testPHPSM');
    $schema->addTable(new \PHPSchemaManager\Objects\Table("blablabla"));
  }
  
}