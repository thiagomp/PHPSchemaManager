<?php
require_once('PHPUnit/Autoload.php');

/**
 * Description of Column
 *
 * @author thiago
 */
class ColumnMysqlTest
  extends PHPUnit_Framework_TestCase {

  /**
   * @expectedException \PHPSchemaManager\Exceptions\ColumnMysqlException
   * @see http://dev.mysql.com/doc/refman/5.0/en/floating-point-types.html
   * @dataProvider setSizeLimitsProvider
   */
  public function testSetSizeLimits($size) {
    $column = new \PHPSchemaManager\Objects\Column('column_' . __FUNCTION__ . "_" . __LINE__);
    $column->setType(\PHPSchemaManager\Objects\Column::FLOAT);
    $column->setSize($size);
    
    $mysqlColumn = new \PHPSchemaManager\Drivers\DriverMysqlColumn($column);
    $mysqlColumn->validateSize($size);
  }

  public function setSizeLimitsProvider() {
    return array(
        array(54),
        array(100),
    );
  }
  
  public function testSetDefaultValueNullForbidden() {
    $column = new \PHPSchemaManager\Objects\Column('column_' . __FUNCTION__ . "_" . __LINE__);
    $column->setType(\PHPSchemaManager\Objects\Column::INT);
    $column->setSize(1);
    $column->forbidsNull();
    $this->assertequals(\PHPSchemaManager\Objects\Column::NODEFAULTVALUE, $column->getDefaultValue());
  }
  
  /**
   * @dataProvider getDataDefinitionDataprovider
   */
  public function testGetDataDefinition($column, $expectedString) {
    $mysqlColumn = new \PHPSchemaManager\Drivers\DriverMysqlColumn($column);
    $this->assertEquals($expectedString, $mysqlColumn->getDataDefinition());
  }
  
  public function getDataDefinitionDataprovider() {
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $column->setSize(10);
    $expectedString = "$columnName BIGINT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::INT);
    $column->setSize(3);
    $column->setDefaultValue(18);
    $expectedString = "$columnName SMALLINT(3) NULL DEFAULT 18 COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::INT);
    $column->setSize(1);
    $column->setDefaultValue(1);
    $column->unsigned();
    $expectedString = "$columnName TINYINT(1) UNSIGNED NULL DEFAULT 1 COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::CHAR);
    $column->setSize(2);
    $column->setDefaultValue("M");
    $expectedString = "$columnName CHAR(2) NULL DEFAULT 'M' COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);

    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
    $column->setSize(250);
    $column->setDefaultValue(\PHPSchemaManager\Objects\Column::NULLVALUE);
    $column->allowsNull();
    $expectedString = "$columnName VARCHAR(250) NULL DEFAULT NULL COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);

    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
    $column->setSize(77);
    $column->allowsNull();
    $column->unsigned();
    $expectedString = "$columnName VARCHAR(77) NULL DEFAULT NULL COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);

    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::DECIMAL);
    $column->setSize("10,3");
    $column->setDefaultValue("");
    $column->allowsNull();
    $expectedString = "$columnName DECIMAL(10,3) NULL DEFAULT NULL COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::FLOAT);
    $column->setSize("5,2");
    $column->setDefaultValue(99.23);
    $expectedString = "$columnName FLOAT(5,2) NULL DEFAULT 99.23 COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::TIMESTAMP);
    $column->forbidsNull();
    $expectedString = "$columnName TIMESTAMP NOT NULL COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::TIMESTAMP);
    $column->setDefaultValue('CURRENT_TIMESTAMP', TRUE);
    $column->forbidsNull();
    $expectedString = "$columnName TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::CHAR);
    $column->setSize(11);
    $column->forbidsNull();
    $column->setDefaultValue(\PHPSchemaManager\Objects\Column::NODEFAULTVALUE);
    $expectedString = "$columnName CHAR(11) NOT NULL COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    $columnName = 'column_' . __FUNCTION__ . "_" . __LINE__;
    $column = new \PHPSchemaManager\Objects\Column($columnName);
    $column->setType(\PHPSchemaManager\Objects\Column::TEXT);
    $column->setSize(10000);
    $column->forbidsNull();
    $column->setDefaultValue(\PHPSchemaManager\Objects\Column::NODEFAULTVALUE);
    $expectedString = "$columnName TEXT(10000) NOT NULL COMMENT 'Created by PHPSchemaManager'";
    $ret[] = array($column, $expectedString);
    
    return $ret;
  }
  
}
