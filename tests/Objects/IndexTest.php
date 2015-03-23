<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of IndexTest
 *
 * @author clyo
 */
class IndexTest
    extends PHPUnit_Framework_TestCase {
    
    /**
     *
     * @var \PHPSchemaManager\Objects\Manager
     */
    protected $sm;
    protected $conn;
    
    const DBTEST = 'PHPSchemaManagerTest';
    
    public function setUp() {
        $conn = new \PHPSchemaManager\Connection();
        $conn->dbms = 'mysql';
        $conn->username = 'root';
        $conn->password = '';
        $conn->hostname = '127.0.0.1';
        $conn->port = '3306';

        $this->sm = \PHPSchemaManager\PHPSchemaManager::getManager($conn);
        $this->sm->setIgnoredSchemas(array('information_schema', 'performance_schema', 'mysql', 'test'));

        $this->conn = $conn;
    }

    public function tearDown() {
        unset($this->sm);
    }
  
  /**
   * Scenario:
   * GIVEN a table that doesnt exists in the schema
   * WHEN a serial column is added
   * THEN a index with primary key will be created
   */
    public function testSerialIsPrimaryIndex()
    {
        $employeeTable = new \PHPSchemaManager\Objects\Table('employee');

        $idColumn = new \PHPSchemaManager\Objects\Column('id');
        $idColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
        $idColumn->setSize(10);
        

        $nameColumn = new \PHPSchemaManager\Objects\Column('name');
        $nameColumn->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
        $nameColumn->setSize(100);
        
        $employeeTable->addColumn($idColumn);
        $employeeTable->addColumn($nameColumn);
        
        $index = $employeeTable->getIndexes();
				$this->assertEquals(1, count($index));
        $this->assertTrue($index[0]->isPrimaryKey());
        
        $s = new \PHPSchemaManager\Objects\Schema(self::DBTEST);
        $this->sm->addSchema($s);
				$s->addTable($employeeTable);
        $this->sm->flush();
    }
  
  /**
   * Scenario:
   * GIVEN a table that doesnt that already exists in the schema
   *   AND this table has a serial column
   * WHEN the table is fetched
   * THEN the table will have a index related to the primary key associated to it
	 * 
	 * @depends testSerialIsPrimaryIndex
   */
    public function testSerialIsPrimaryIndexAfterPersisted()
    {
        $employeeTable = $this->sm->hasSchema(self::DBTEST)->hasTable('employee');
        
        $index = $employeeTable->getIndexes();
				$this->assertEquals(1, count($index));
        $this->assertTrue($index[0]->isPrimaryKey());
    }
    
  /**
   * Scenario:
   * GIVEN a table that doesn't exists in the schema with a custom index
   * WHEN persisting the table
   * THEN the index will be created
   */
  public function testUniqueIndex() {
      
        $employeeTable = $this->sm->hasSchema(self::DBTEST)->hasTable('employee');

        $birthDateColumn = new \PHPSchemaManager\Objects\Column('birthDate');
        $birthDateColumn->setType(\PHPSchemaManager\Objects\Column::DATETIME);

        $index = new \PHPSchemaManager\Objects\Index('idxNewComposed');
        $index->setAsUniqueKey();
        $index->addColumn($employeeTable->hasColumn('name'));
        $index->addColumn($birthDateColumn);
        
        $employeeTable->addColumn($birthDateColumn);
        $employeeTable->addIndex($index);
        
        $this->sm->hasSchema(self::DBTEST)->flush();
        $table = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn)->hasSchema(self::DBTEST)->hasTable('employee');
        $idx = $table->hasIndex('idxNewComposed');
        $this->assertTrue(is_a($idx, '\PHPSchemaManager\Objects\Index'), "Was expecting a Index object");
        $this->assertTrue($idx->isUniqueKey(), "Was expecting a 'Unique' index, but got a '" . $idx->getType() . "' type of index");
  }
}
