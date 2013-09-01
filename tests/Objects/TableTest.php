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

      $bookAuthorId = new \PHPSchemaManager\Objects\Column('authorId');
      $bookAuthorId->setType(\PHPSchemaManager\Objects\Column::INT);
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

      $orderBookId = new \PHPSchemaManager\Objects\Column('bookId');
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
      $orderCustomerId->references($customerTable->hasColumn('id'))->actionOnDelete(\PHPSchemaManager\Objects\ColumnReference::NOACTION);
      $this->assertEquals(\PHPSchemaManager\Objects\ColumnReference::NOACTION,
          $orderCustomerId->getReference()->getActionOnDelete(),
          'the customerId field is expected to ignore when a deletion is onde');

      $orderTable = new \PHPSchemaManager\Objects\Table('order');
      $orderTable->addColumn($orderNo);
      $orderTable->addColumn($orderBookId);
      $orderTable->addColumn($orderAuthorId);
      $orderTable->addColumn($orderCustomerId);
  }
}
