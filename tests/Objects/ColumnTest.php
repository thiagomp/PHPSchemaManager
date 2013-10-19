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

        $this->assertFalse($newColumn->isNullAllowed(), "SERIAL columns should not allow NULL values");
        $this->assertFalse($newColumn->isSigned(), "SERIAL columns are supposed to be unsigned by default");
        $this->assertEquals(10, $newColumn->getSize(), "SERIAL columns are expected to have 10 as its size");
        $this->assertEquals(\PHPSchemaManager\Objects\Column::NODEFAULTVALUE, $newColumn->getDefaultValue(),
            "SERIAL columns are expected to not held a default value");
    }

    public function testCarbonCopy() {

        $authorId = new \PHPSchemaManager\Objects\Column('id');
        $authorId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

        $bookAuthorId = $authorId->carbonCopy('authorId');

        $this->assertInstanceOf('\PHPSchemaManager\Objects\Column', $bookAuthorId);
        $this->assertEquals('authorId', $bookAuthorId->getName());
        $this->assertEquals(\PHPSchemaManager\Objects\Column::SERIAL, $bookAuthorId->getType());
        $this->assertEquals(10, $bookAuthorId->getSize(), "the duplicated column is expected to have 10 as its size");
        $this->assertFalse($bookAuthorId->isSigned(), "the duplicated column is expected to be unsigned");
        $this->assertFalse($bookAuthorId->isNullAllowed(),
            "the duplicated column is expected to not allow null values");
        $this->assertEquals(\PHPSchemaManager\Objects\Column::NODEFAULTVALUE, $bookAuthorId->getDefaultValue(),
            "id column is expected to not have default value");
    }

}
