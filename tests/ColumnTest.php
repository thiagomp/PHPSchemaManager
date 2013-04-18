<?php
require_once('PHPUnit/Autoload.php');

/**
 * Description of ColumnTest
 *
 * @author thiago
 */
class ColumnTest 
  extends PHPUnit_Framework_TestCase {
  
  /**
   * @dataProvider setSizeProvider
   */
  public function testSetSize($size, $type, $expected) {
    $column = new \PHPSchemaManager\Objects\Column('column_' . __FUNCTION__ . "_" . __LINE__);
    $column->setType($type);
    $column->setSize($size);
    $this->assertEquals($expected, $column->getSize());
  }
  
  public function setSizeProvider() {
    $ret[] = array("5,2", \PHPSchemaManager\Objects\Column::FLOAT,   "5,2");
    $ret[] = array("7",   \PHPSchemaManager\Objects\Column::FLOAT,   "7,0");
    $ret[] = array(10,    \PHPSchemaManager\Objects\Column::INT,     10);
    $ret[] = array(10,    \PHPSchemaManager\Objects\Column::SERIAL,  10);
    $ret[] = array(70,    \PHPSchemaManager\Objects\Column::DECIMAL, "70,0");
    return $ret;
  }
  
  /**
   * @expectedException \PHPSchemaManager\Exceptions\ColumnException
   * @dataProvider setDefaultValueSizeExceptionProvider
   */
  public function testSetDefaultValueSizeException(\PHPSchemaManager\Objects\Column $column, $defaultValue) {
    $column->setDefaultValue($defaultValue);
  }
  
  public function setDefaultValueSizeExceptionProvider() {
    $column = new \PHPSchemaManager\Objects\Column('column_' . __FUNCTION__ . "_" . __LINE__);
    $column->setType(\PHPSchemaManager\Objects\Column::INT);
    $column->setSize(1);
    $ret[] = array($column, 10);

    $column = new \PHPSchemaManager\Objects\Column('column_' . __FUNCTION__ . "_" . __LINE__);
    $column->setType(\PHPSchemaManager\Objects\Column::CHAR);
    $column->setSize(1);
    $ret[] = array($column, "female");
    
    return $ret;
  }
  
  public function testNewColumnSerial() {
    $newColumn = new \PHPSchemaManager\Objects\Column('id');
    $newColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $newColumn->setSize(10);
    
    $this->assertTrue(!$newColumn->isNullAllowed(), "SERIAL columns should not allow NULL values");
  }

}
