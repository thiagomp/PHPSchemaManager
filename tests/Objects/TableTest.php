<?php

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

    public function testForeignKey() {

        $authorId = new \PHPSchemaManager\Objects\Column('id');
        $authorId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

        $authorName = new \PHPSchemaManager\Objects\Column('name');
        $authorName->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
        $authorName->setSize(100);

        $authorTable = new \PHPSchemaManager\Objects\Table('author');
        $authorTable->addColumn($authorId);
        $authorTable->addColumn($authorName);

        $bookId = new \PHPSchemaManager\Objects\Column('id');
        $bookId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

        // Create the column object, but all the configuration of this field will be copyed from the referenced
        //  object by the references method
        $bookAuthorId = new \PHPSchemaManager\Objects\Column('authorId');
        $bookAuthorId->references($authorTable->hasColumn('id'));

        $this->assertInstanceOf('\PHPSchemaManager\Objects\ColumnReference', $bookAuthorId->getReference());
        $this->assertEquals('id', $bookAuthorId->getReferencedColumn()->getName(), 'The column id from the author table was expected to be referenced');

        $bookTitle = new \PHPSchemaManager\Objects\Column('title');
        $bookTitle->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
        $bookTitle->setSize(250);

        $bookTable = new \PHPSchemaManager\Objects\Table('book');
        $bookTable->addColumn($bookId);
        $bookTable->addColumn($bookAuthorId);
        $bookTable->addColumn($bookTitle);

        $customerId = new \PHPSchemaManager\Objects\Column('id');
        $customerId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

        $customerTable = new \PHPSchemaManager\Objects\Table('customer');
        $customerTable->addColumn($customerId);

        $orderNo = new \PHPSchemaManager\Objects\Column('id');
        $orderNo->setType(\PHPSchemaManager\Objects\Column::SERIAL);

        // Here a different technique of creation will be used.
        // A clone of the object that will be referenced will be created than a reference will be done
        $orderBookId = $bookTable->hasColumn('id')->carbonCopy('bookId');
        $orderBookId->references($bookTable->hasColumn('id'));

        $orderAuthorId = new \PHPSchemaManager\Objects\Column('authorId');
        $orderAuthorId->references($bookTable->hasColumn('authorId'));

        $this->assertInstanceOf('\PHPSchemaManager\Objects\ColumnReference', $orderBookId->getReference());
        $this->assertEquals('id', $orderBookId->getReferencedColumn()->getName(), 'The column id from the author table was expected to be referenced');
        $this->assertEquals(\PHPSchemaManager\Objects\ColumnReference::CASCADE,
            $orderBookId->getReference()->getActionOnUpdate(),
            'When a object is referenced, the updates should be cascaded by default');
        $this->assertEquals(\PHPSchemaManager\Objects\ColumnReference::CASCADE,
            $orderBookId->getReference()->getActionOnDelete(),
            'When a object is referenced, the deletions should be cascaded by default');

        $orderCustomerId = new \PHPSchemaManager\Objects\Column('customerId');

        $orderTable = new \PHPSchemaManager\Objects\Table('order');
        $orderTable->addColumn($orderNo);
        $orderTable->addColumn($orderBookId);
        $orderTable->addColumn($orderAuthorId);
        $orderTable->addColumn($orderCustomerId);

        $orderCustomerId = $orderTable->hasColumn('customerId');
        $orderCustomerId->references($customerTable->hasColumn('id'))->actionOnDelete(\PHPSchemaManager\Objects\ColumnReference::NOACTION);
        $this->assertEquals(\PHPSchemaManager\Objects\ColumnReference::NOACTION,
            $orderTable->hasColumn('customerId')->getReference()->getActionOnDelete(),
            'the customerId field is expected to ignore when a deletion is onde');
    }

    public function testTableEngine() {
        $authorId = new \PHPSchemaManager\Objects\Column('id');
        $authorId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

        $authorName = new \PHPSchemaManager\Objects\Column('name');
        $authorName->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
        $authorName->setSize(100);

        $authorTable = new \PHPSchemaManager\Objects\Table('author');
        $authorTable->addColumn($authorId);
        $authorTable->addColumn($authorName);

        $specifics = new \PHPSchemaManager\Drivers\TableSpecificMysql();
        $specifics->markAsInnoDb();

        $authorTable->addSpecificConfiguration($specifics);

        $this->assertEquals(1, count($authorTable->getSpecificsConfiguration()));
        $this->assertTrue($specifics->isInnoDb());
    }
    
    /**
     * Scenario:
     * GIVEN a Index that doesn't have any columns associated to it
     * WHEN the given Index is added to a Table
     * THEN the Table will trigger an Exception
     * 
     * @expectedException \PHPSchemaManager\Exceptions\TableException
     */
    public function testAddIndexWithoutColumn() {
        $index = new \PHPSchemaManager\Objects\Index('unitTestIndex');
        
        $authorId = new \PHPSchemaManager\Objects\Column('id');
        $authorId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

        $authorName = new \PHPSchemaManager\Objects\Column('name');
        $authorName->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
        $authorName->setSize(100);

        $authorTable = new \PHPSchemaManager\Objects\Table('author');
        $authorTable->addColumn($authorId);
        $authorTable->addColumn($authorName);
        $authorTable->addIndex($index);
    }
}
