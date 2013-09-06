<?php
require_once('PHPUnit/Autoload.php');

class ManagerTest
  extends PHPUnit_Framework_TestCase {

  /**
   *
   * @var \PHPSchemaManager\Objects\Manager
   */
  protected $sm;
  protected $conn;

  protected $memoryUsageFile;
  protected $memoryUsageFilePointer;

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

  public function testState() {
    $authorTable = new \PHPSchemaManager\Objects\Table('author');
    $this->assertTrue($authorTable->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");

    $idColumn = new \PHPSchemaManager\Objects\Column('id');
    $this->assertTrue($idColumn->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");
    $idColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $this->assertTrue($idColumn->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");
    $idColumn->setSize(10);
    $this->assertTrue($idColumn->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");

    $authorTable->addColumn($idColumn);
    $this->assertTrue($authorTable->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");

    // let's say that table is flushed into database...
    $authorTable->persisted();
    $this->assertTrue($authorTable->isSynced(), "It should be [alter], but it is [" . $this->getStatus() . "]");
    $idColumn->persisted();
    $this->assertTrue($idColumn->isSynced(), "It should be [alter], but it is [" . $this->getStatus() . "]");

    // now let's insert a new column in the table
    $nameColumn = new \PHPSchemaManager\Objects\Column('name');
    $this->assertTrue($nameColumn->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");
    $nameColumn->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
    $this->assertTrue($nameColumn->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");
    $nameColumn->setSize(10);
    $this->assertTrue($nameColumn->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");

    $authorTable->addColumn($nameColumn);
    $this->assertTrue($authorTable->shouldAlter(), "It should be [alter], but it is [" . $this->getStatus() . "]");

    $nameColumn->setSize(11);
    $this->assertTrue($nameColumn->shouldCreate(), "It should be [create], but it is [" . $this->getStatus() . "]");

    $authorTable->persisted();
    $this->assertTrue($authorTable->isSynced(), "It should be [alter], but it is [" . $this->getStatus() . "]");
    $nameColumn->persisted();
    $this->assertTrue($nameColumn->isSynced(), "It should be [synced], but it is [" . $this->getStatus() . "]");

    $nameColumn->setSize(11);
    $this->assertTrue($nameColumn->shouldAlter(), "It should be [alter], but it is [" . $this->getStatus() . "]");
    $this->assertTrue($authorTable->shouldAlter(), "It should be [alter], but it is [" . $this->getStatus() . "]");

    $nameColumn->persisted();
    $this->assertTrue($nameColumn->isSynced(), "It should be [synced], but it is [" . $this->getStatus() . "]");

    $authorTable->dropColumn("name");
    $this->assertTrue($nameColumn->shouldDelete(), "It should be [delete], but it is [" . $this->getStatus() . "]");
    $this->assertTrue($authorTable->shouldAlter(), "It should be [alter], but it is [" . $this->getStatus() . "]");

    $nameColumn->markAsDeleted();
    $this->assertTrue($nameColumn->isDeleted(), "It should be [deleted], but it is [" . $this->getStatus() . "]");
  }

  public function testCreateTable() {
    // create book table
    $bookTable = new \PHPSchemaManager\Objects\Table('book');

    $bookIdColumn = new \PHPSchemaManager\Objects\Column('id');
    $bookIdColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $bookIdColumn->setSize(10);

    $bookTable->addColumn($bookIdColumn);

    // create wrongTable table
    $wrongTable = new \PHPSchemaManager\Objects\Table("wrongTable");

    $wrongAgeColumn = new \PHPSchemaManager\Objects\Column('wrongAge');
    $wrongAgeColumn->setType(\PHPSchemaManager\Objects\Column::INT);

    $wrongAgeIdColumn = new \PHPSchemaManager\Objects\Column('id');
    $wrongAgeIdColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $wrongAgeIdColumn->setSize(10);

    $wrongTable->addColumn($wrongAgeIdColumn);
    $wrongTable->addColumn($wrongAgeColumn);


    // add tables to the schema
    $schema = $this->sm->createNewSchema(self::DBTEST);
    $schema->addTable($bookTable);
    $schema->addTable($wrongTable);


    // add the schema to the manager
    $this->sm->addSchema($schema);

    // check if the schema can be found before the flush
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Schema', $this->sm->hasSchema(self::DBTEST));

    // check if the tables can be found in the schema before the flush
    $this->assertEquals(2, $this->sm->hasSchema(self::DBTEST)->countTables());

    // commit the changes to the database
    $schema->flush();

    // check if the object is correctly updated
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Table', $schema->hasTable('book'), "Failed to find the 'book' table");
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index', $schema->hasTable('book')->hasIndex('PRIMARY'), "Failed to find index 'PRIMARY' in the table 'book'");
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Table', $schema->hasTable('wrongTable'), "Failed to find the 'wrongTable' table");
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index', $schema->hasTable('wrongTable')->hasIndex('PRIMARY'), "Failed to find index 'PRIMARY' in the table 'wrongTable'");
    $this->assertTrue($schema->hasTable("book")->isSynced(), "The 'book' table should be synced");
    $this->assertTrue($schema->hasTable("wrongTable")->isSynced(), "The 'wrongTable' table should be synced");

    // check if accessing the data directly will also be correctly updated
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Table', $this->sm->hasSchema(self::DBTEST)->hasTable('book'), "Failed to find the 'book' table (direct)");
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index', $this->sm->hasSchema(self::DBTEST)->hasTable('book')->hasIndex('PRIMARY'), "Failed to find index 'PRIMARY' in the table 'book'");
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Table', $this->sm->hasSchema(self::DBTEST)->hasTable('wrongTable'), "Failed to find the 'wrongTable' table (direct)");
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index', $this->sm->hasSchema(self::DBTEST)->hasTable('wrongTable')->hasIndex('PRIMARY'), "Failed to find index 'PRIMARY' in the table 'wrongTable' (direct)");
    $this->assertTrue($this->sm->hasSchema(self::DBTEST)->hasTable("book")->isSynced(), "The 'book' table should be synced (direct)");
    $this->assertTrue($this->sm->hasSchema(self::DBTEST)->hasTable("wrongTable")->isSynced(), "The 'wrongTable' table should be synced (direct)");
  }

  public function testSchemaCanBeFound() {
    $m = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);
    $this->assertInstanceOf("\PHPSchemaManager\Objects\Manager", $m);
  }

  public function testExclusiveSchema() {
    $m = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);

    $m->setExclusiveSchema('schemaTestA');

    $schemasTest = array('schemaTestA', 'schemaTestB', 'schemaTestC');

    foreach($schemasTest as $schemaName) {
      $schema = new \PHPSchemaManager\Objects\Schema($schemaName);
      $m->addSchema($schema);
    }
    $m->flush();

    $this->assertTrue($m->hasSchema('schemaTestB')->shouldBeIgnored(), "The schema 'schemaTestB' should be ignored");
    $this->assertTrue($m->hasSchema('schemaTestC')->shouldBeIgnored(), "The schema 'schemaTestC' should be ignored");
    $this->assertFalse($m->hasSchema('schemaTestA')->shouldBeIgnored(), "The schema 'schemaTestA' should not be ignored");

    $m->hasSchema('schemaTestA')->drop();

    $m->flush();
  }

  public function testExclusiveAndIgnoredSchema() {
    $m = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);

    $m->setIgnoredSchemas(array('schemaTestB', 'schemaTestC', 'schemaTestD'));
    $m->setExclusiveSchema('schemaTestE');

    $schemasTest = array('schemaTestA', 'schemaTestB', 'schemaTestC', 'schemaTestD', 'schemaTestE');

    foreach($schemasTest as $schemaName) {
      $schema = new \PHPSchemaManager\Objects\Schema($schemaName);
      $m->addSchema($schema);
    }
    $m->flush();

    $this->assertTrue($m->hasSchema('schemaTestA')->shouldBeIgnored(), "The schema 'schemaTestB' should be ignored");
    $this->assertTrue($m->hasSchema('schemaTestB')->shouldBeIgnored(), "The schema 'schemaTestC' should be ignored");
    $this->assertTrue($m->hasSchema('schemaTestC')->shouldBeIgnored(), "The schema 'schemaTestB' should be ignored");
    $this->assertTrue($m->hasSchema('schemaTestD')->shouldBeIgnored(), "The schema 'schemaTestC' should be ignored");
    $this->assertFalse($m->hasSchema('schemaTestE')->shouldBeIgnored(), "The schema 'schemaTestA' should not be ignored");

    $m->hasSchema('schemaTestE')->drop();

    $m->flush();
  }

  public function testReplaceTable() {
    // create the table that will be duplicated
    $duplicatedTable = new \PHPSchemaManager\Objects\Table('duplicatedTable');

    $duplicatedAColumn = new \PHPSchemaManager\Objects\Column('columnA');
    $duplicatedAColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $duplicatedAColumn->setSize(9);

    $duplicatedBColumn = new \PHPSchemaManager\Objects\Column('columnB');
    $duplicatedBColumn->setType(\PHPSchemaManager\Objects\Column::CHAR);
    $duplicatedBColumn->setSize(3);
    $duplicatedBColumn->setDefaultValue('B');

    $duplicatedCColumn = new \PHPSchemaManager\Objects\Column('columnC');
    $duplicatedCColumn->setType(\PHPSchemaManager\Objects\Column::DECIMAL);
    $duplicatedCColumn->setSize('3,2');

    $duplicatedTable->addColumn($duplicatedAColumn);
    $duplicatedTable->addColumn($duplicatedBColumn);
    $duplicatedTable->addColumn($duplicatedCColumn);

    $this->sm->hasSchema(self::DBTEST)->addTable($duplicatedTable);
    $this->sm->flush();

    // create the duplicated table
    $duplicatedTable2 = new \PHPSchemaManager\Objects\Table('duplicatedTable');

    $duplicated2AColumn = new \PHPSchemaManager\Objects\Column('columnA');
    $duplicated2AColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $duplicated2AColumn->setSize(10);

    $duplicated2BColumn = new \PHPSchemaManager\Objects\Column('columnB');
    $duplicated2BColumn->setType(\PHPSchemaManager\Objects\Column::CHAR);
    $duplicated2BColumn->setSize(3);
    $duplicated2BColumn->setDefaultValue('B');

    $duplicatedTable2->addColumn($duplicated2AColumn);
    $duplicatedTable2->addColumn($duplicated2BColumn);

    $this->sm->hasSchema(self::DBTEST)->addTable($duplicatedTable2, TRUE);
    $this->sm->flush();

    $this->assertEquals(2,
            $this->sm->hasSchema(self::DBTEST)->hasTable('duplicatedTable')->countColumns(),
            "After the replacement, 'duplicatedTable' was expected to have 2 Columns");

    $this->assertEquals(10,
            $this->sm->hasSchema(self::DBTEST)->hasTable('duplicatedTable')->hasColumn('columnA')->getSize(),
            "Column 'id' is expected to have its size configured to be 10");

    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index',
            $this->sm->hasSchema(self::DBTEST)->hasTable('duplicatedTable')->hasIndex('PRIMARY'),
            'Check if the table have a PRIMARY key');

    $this->assertEquals(1,
            $this->sm->hasSchema(self::DBTEST)->hasTable('duplicatedTable')->countIndexes(),
            "'duplicatedTable' is expected to have only 1 index");

    $this->sm->hasSchema(self::DBTEST)->dropTable('duplicatedTable');
    $this->sm->flush();
  }

  /**
   * @dataProvider addColumnProvider
   */
  public function testAddColumn($column, $name) {
    // get the schema
    $s = $this->sm->hasSchema(self::DBTEST);

    // get the book table
    $bookTable = $s->hasTable('book');
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Table', $bookTable, "Failed to find the book table");

    // add the new Column to the users table
    $bookTable->addColumn($column);

    // Check if the Column title exists before the flush
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Column', $bookTable->hasColumn($name), "Failed to find the $name column in the book table");

    // save the data
    $this->sm->flush();
  }

  public function addColumnProvider() {
    $name = 'title';
    $newColumn = new \PHPSchemaManager\Objects\Column($name);
    $newColumn->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
    $newColumn->setSize(100);
    $newColumn->forbidsNull();
    $ret[] = array($newColumn, $name);

    $name = 'isbn';
    $newColumn = new \PHPSchemaManager\Objects\Column($name);
    $newColumn->setType(\PHPSchemaManager\Objects\Column::CHAR);
    $newColumn->setSize(13);
    $newColumn->forbidsNull();
    $ret[] = array($newColumn, $name);

    $name = 'language';
    $newColumn = new \PHPSchemaManager\Objects\Column($name);
    $newColumn->setType(\PHPSchemaManager\Objects\Column::CHAR);
    $newColumn->setSize(3);
    $newColumn->allowsNull();
    $newColumn->setDefaultValue("EN");
    $ret[] = array($newColumn, $name);

    $name = 'wrongColumn';
    $newColumn = new \PHPSchemaManager\Objects\Column($name);
    $newColumn->setType(\PHPSchemaManager\Objects\Column::INT);
    $newColumn->setSize(8);
    $ret[] = array($newColumn, $name);

    $name = 'summary';
    $newColumn = new \PHPSchemaManager\Objects\Column($name);
    $newColumn->setType(\PHPSchemaManager\Objects\Column::TEXT);
    $newColumn->setSize(10000);
    $ret[] = array($newColumn, $name);

    return $ret;
  }

  public function testChangeColumn() {
    // get the schema
    $s = $this->sm->hasSchema(self::DBTEST);

    $bookTable = $s->hasTable("book");

    $this->assertInstanceOf("\PHPSchemaManager\Objects\Table", $bookTable, "The table book wasn't found");

    $isbnColumn = $bookTable->hasColumn("isbn");
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Column', $isbnColumn, "Failed to check if the object is a column ");
    $isbnColumn->setSize(11);

    $this->assertTrue($isbnColumn->shouldAlter(), "Isbn column is expected to be altered in the database");
    $this->assertTrue($bookTable->shouldAlter(), "Book column is expected to be altered in the database because isbn columns was changed");
    $this->assertEquals(11, $isbnColumn->getSize(), "Check if the value is correct, before to flush the alter to the database");
    $this->sm->flush();
    $this->assertTrue($isbnColumn->isSynced(), "isbn column should be synced now, but it is marked to " . $isbnColumn->getAction());

    // try to change altogether
    $this->sm->hasSchema(self::DBTEST)->hasTable("book")->hasColumn("title")->setSize(222);
    $this->assertTrue($this->sm->hasSchema(self::DBTEST)->hasTable("book")->hasColumn("isbn")->isSynced(), "isbn column should stay synced, but it is marked to " . $this->sm->hasSchema(self::DBTEST)->hasTable("book")->hasColumn("isbn")->getAction() . " (2)");
    $this->assertEquals(222, $this->sm->hasSchema(self::DBTEST)->hasTable("book")->hasColumn("title")->getSize(), "Check if the title size is correct by doing the operation altogether");
    $this->sm->flush();

    $newSm = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);
    $newSm->setIgnoredSchemas(array('information_schema', 'performance_schema', 'mysql', 'test'));
    $this->assertEquals(222, $newSm->hasSchema(self::DBTEST)->hasTable("book")->hasColumn("title")->getSize(), "Check if the title size was correctly saved to the database after changing it by doing an operation altogether");

    // try another change
    $this->sm->hasSchema(self::DBTEST)->hasTable("book")->hasColumn("language")->forbidsNull();
    $this->assertFalse($this->sm->hasSchema(self::DBTEST)->hasTable("book")->hasColumn("language")->isNullAllowed(), "Check if the column language accepts Null now");
    $this->sm->flush();

    $newSm = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);
    $newSm->setIgnoredSchemas(array('information_schema', 'performance_schema', 'mysql', 'test'));
    $this->assertFalse($newSm->hasSchema(self::DBTEST)->hasTable("book")->hasColumn("language")->isNullAllowed(), "Check if the colum accepts Null after the change was persisted in the database");
  }

  public function testDropColumn() {
    $bookTable = $this->sm->hasSchema(self::DBTEST)->hasTable("book");

    $this->assertInstanceOf("\PHPSchemaManager\Objects\Table", $bookTable, "The table book wasn't found");

    $bookTable->dropColumn('wrongColumn');

    $this->assertFalse($this->sm->hasSchema(self::DBTEST)->hasTable('book')->hasColumn('wrongColumn'), 'Check if the wrongColumn was removed from the table');

    $this->sm->flush();

    $this->assertFalse($this->sm->hasSchema(self::DBTEST)->hasTable('book')->hasColumn('wrongColumn'), 'Check if the wrongColumn was really removed from the table in tthe database');
  }

  public function testAddIndex() {
    $bookTable = $this->sm->hasSchema(self::DBTEST)->hasTable("book");

    $this->assertInstanceOf("\PHPSchemaManager\Objects\Table", $bookTable, "The table book wasn't found");

    $bookIsbnIdx = new \PHPSchemaManager\Objects\Index('bookIsbnIdx');
    $bookIsbnIdx->setAsUniqueKey();
    $bookIsbnIdx->addColumn($bookTable->hasColumn('isbn'));

    $this->assertTrue($bookIsbnIdx->shouldCreate());
    $this->assertTrue($bookIsbnIdx->isUniqueKey());
    $this->assertEquals(1, $bookIsbnIdx->countColumns());

    $bookTable->addIndex($bookIsbnIdx);

    $this->sm->flush();
    $this->assertTrue($bookIsbnIdx->isSynced(), "The status os the index should be synced by now");
    $this->assertTrue($bookIsbnIdx->isUniqueKey());
    $this->assertEquals(1, $bookIsbnIdx->countColumns());

    // retrieve the index into a new object
    $index = $this->sm->hasSchema(self::DBTEST)->hasTable('book')->hasIndex('bookIsbnIdx');
    $this->assertTrue($index->isSynced());
    $this->assertTrue($index->isUniqueKey(), "the type of this index is actually {$index->getType()} instead of Unique");
    $this->assertEquals(1, $index->countColumns());

    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index', $bookTable->hasIndex('bookIsbnIdx'), "Check if the table book has an index called bookIsbnIdx");

    // add this one to be removed later
    $wrongIndex = new \PHPSchemaManager\Objects\Index('wrongIdx');
    $wrongIndex->setAsRegularKey();
    $wrongIndex->addColumn($bookTable->hasColumn('title'));
    $this->sm->hasSchema(self::DBTEST)->hasTable('book')->addIndex($wrongIndex);
    $this->sm->flush();
    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index', $bookTable->hasIndex('wrongIdx'), "Check if the table book has an index called wrongIdx");

  }

  public function testAddMultipleIndex() {
    $bookTable = $this->sm->hasSchema(self::DBTEST)->hasTable('book');

    $titleIdx = new \PHPSchemaManager\Objects\Index('titleIdx');
    $titleIdx->addColumn($bookTable->hasColumn('title'));

    $isbnIdx = new \PHPSchemaManager\Objects\Index('isbnIdx');
    $isbnIdx->addColumn($bookTable->hasColumn('isbn'));
    $isbnIdx->setAsUniqueKey();

    $multIdx = new \PHPSchemaManager\Objects\Index('multIdx');
    $multIdx->addColumn($bookTable->hasColumn('title'));
    $multIdx->addColumn($bookTable->hasColumn('language'));

    $bookTable->addIndex($titleIdx);
    $bookTable->addIndex($isbnIdx);
    $bookTable->addIndex($multIdx);

    $this->sm->flush();

    $this->assertEquals(6, $this->sm->hasSchema(self::DBTEST)->hasTable('book')->countIndexes(), "'book' table was expected to have 5 indexes\n" . $this->sm->hasSchema(self::DBTEST)->hasTable('book')->printTxt());
    $this->assertTrue($this->sm->hasSchema(self::DBTEST)->hasTable('book')->hasIndex('titleIdx')->isRegularKey(), "'titleIdx' index is expected to be a Regular one");
    $this->assertTrue($this->sm->hasSchema(self::DBTEST)->hasTable('book')->hasIndex('isbnIdx')->isUniqueKey(), "'isbnIdx' index is expected to be a Unique Key index");
    $this->assertEquals(2, $this->sm->hasSchema(self::DBTEST)->hasTable('book')->hasIndex('multIdx')->countColumns(), "'multIdx' index is expected to have 2 columns associated with it");
  }

  public function testAddRepeatedIndex() {
    $bookTable = $this->sm->hasSchema(self::DBTEST)->hasTable('book');

    $isbnIdx = new \PHPSchemaManager\Objects\Index('isbnIdx');
    $isbnIdx->addColumn($bookTable->hasColumn('isbn'));
    $bookTable->addIndex($isbnIdx);

    $this->sm->flush();

    $this->assertEquals(6, $this->sm->hasSchema(self::DBTEST)->hasTable('book')->countIndexes(), "'book' table was expected to have 5 indexes\n" . $this->sm->hasSchema(self::DBTEST)->hasTable('book')->printTxt());
    $this->assertTrue($this->sm->hasSchema(self::DBTEST)->hasTable('book')->hasIndex('isbnIdx')->isRegularKey(), "'isbnIdx' index is expected to be a Regular one");
  }

  public function testChangeIndex() {
    $bookTable = $this->sm->hasSchema(self::DBTEST)->hasTable("book");

    $this->assertInstanceOf("\PHPSchemaManager\Objects\Table", $bookTable, "The table book wasn't found");

    $bookIsbnIdx = $bookTable->hasIndex('bookIsbnIdx');

    $this->assertInstanceOf("\PHPSchemaManager\Objects\Index", $bookIsbnIdx, "The index bookIsbnIdx wasn't found");

    $this->assertTrue($bookIsbnIdx->isUniqueKey(), "the type of this index is actually {$bookIsbnIdx->getType()} instead of Unique");

    // insert a new column to this key
    $bookIsbnIdx->addColumn($bookTable->hasColumn('title'));

    $this->assertTrue($bookIsbnIdx->shouldAlter());
    $this->assertEquals(2, $bookIsbnIdx->countColumns());

    $this->sm->flush();
    $this->assertTrue($bookIsbnIdx->isSynced(), "The status os the index should be synced by now");
    $this->assertTrue($bookIsbnIdx->isUniqueKey());
    $this->assertEquals(2, $bookIsbnIdx->countColumns());

    $this->assertInstanceOf('\PHPSchemaManager\Objects\Index', $bookTable->hasIndex('bookIsbnIdx'), "Check if the table book has an index called bookIsbnIdx after the change");
  }

  public function testPrintJSON() {
    $json = $this->sm->hasSchema(self::DBTEST)->printJSON();
    $this->assertNotNull(json_decode($json));

    // This was necessary to cover the differences that can happen because
    // linux is case sensitive and windows not.
    // The idea is more to check if all elements are here
    $json = strtolower($json);
    $this->assertJsonStringEqualsJsonFile(__DIR__ . DIRECTORY_SEPARATOR . "db_test.json", $json);
  }

  public function testSaveJSON() {
    $expectedFile = __DIR__ . DIRECTORY_SEPARATOR . "file_test.json";
    $json = $this->sm->hasSchema(self::DBTEST)->printJSON();
    $this->sm->hasSchema(self::DBTEST)->saveSchemaJSON($expectedFile);

    $this->assertFileExists($expectedFile);
    $this->assertJsonStringEqualsJsonFile($expectedFile, $json);

    unlink($expectedFile);
  }

  public function testDropIndex() {
    $bookTable = $this->sm->hasSchema(self::DBTEST)->hasTable("book");
    $this->assertInstanceOf("\PHPSchemaManager\Objects\Table", $bookTable, "The table book wasn't found");

    $this->sm->hasSchema(self::DBTEST)->hasTable('book')->dropIndex('wrongIdx');
    $this->assertFalse($bookTable->hasIndex('wrongIdx'));
    $this->sm->flush();

    $newSm = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);
    $newSm->setIgnoredSchemas(array('information_schema', 'performance_schema', 'mysql', 'test'));
    $this->assertFalse($newSm->hasSchema(self::DBTEST)->hasTable('book')->hasIndex('wrongIdx'));
  }

  public function testDropTable() {
    $bookTable = $this->sm->hasSchema(self::DBTEST)->hasTable('book');

    $this->assertTrue($this->sm->hasSchema(self::DBTEST)->dropTable('book'), "Trying to remove book table");
    $this->assertFalse($this->sm->hasSchema(self::DBTEST)->hasTable('book'), "Validating that book table is not available anymore");
    $this->assertTrue($bookTable->shouldDelete(), "The object should be marked to be deleted");

    // commit the delete to the database
    $this->sm->flush();

    $newSm = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);
    $newSm->setIgnoredSchemas(array('information_schema', 'performance_schema', 'mysql', 'test'));
    $this->assertFalse($newSm->hasSchema(self::DBTEST)->hasTable('book'), "Check if the book table was really dropped from the database");
    $this->assertTrue($bookTable->isDeleted(), "The object should be marked as deleted");
    $this->assertFalse($bookTable->hasColumn('isbn'), "Columns from the deleted table cannot be found by hasColumn");
    $this->assertFalse($bookTable->hasIndex('bookIsbnIdx'), "Indexes from the deleted table cannot be found by hasIndex");
  }

  public function testColumnNameConflict() {
    $wrongTable = $this->sm->hasSchema(self::DBTEST)->hasTable('wrongTable');

    // mark wrongAge Column to be deleted
    $wrongTable->dropColumn('wrongAge');

    // now creates another wrongAge Column ...
    $wrongAgeColumn = new \PHPSchemaManager\Objects\Column('wrongAge');
    $wrongAgeColumn->setType(\PHPSchemaManager\Objects\Column::DATETIME);

    // ... and tries to add it to the table
    $wrongTable->addColumn($wrongAgeColumn);


    $this->assertInstanceOf('\PHPSchemaManager\Objects\Column', $wrongTable->hasColumn('wrongAge'), "The Column 'wrongAge' should exist");
    $this->assertEquals(\PHPSchemaManager\Objects\Column::DATETIME, $wrongTable->hasColumn('wrongAge')->getType(), "'DATETIME' was the type expected for this Column now");

  }

  public function testTableNameConflict() {
    // mark a table for deletion
    $this->sm->hasSchema(self::DBTEST)->dropTable("wrongTable");

    $wrongTable = new \PHPSchemaManager\Objects\Table("wrongTable");
    $newColumn = new \PHPSchemaManager\Objects\Column('id');
    $newColumn->setType(\PHPSchemaManager\Objects\Column::SERIAL);
    $newColumn->setSize(10);
    $wrongTable->addColumn($newColumn);

    // try to create a table with the same name
    $this->sm->hasSchema(self::DBTEST)->addTable($wrongTable);

    // check if this table have only one column. The firstly created table had 2 columns
    $this->assertEquals(1, $this->sm->hasSchema(self::DBTEST)->hasTable("wrongTable")->countColumns());

    // commit the changes
    $this->sm->flush();

    // check if the change was really committed
    $this->assertTrue($this->sm->hasSchema(self::DBTEST)->isSynced(),
            "Schema '".self::DBTEST."' was expected to be marked as synced, but it is actually marked as '".$this->sm->hasSchema(self::DBTEST)->getAction()."'");
  }

  public function testDropSchema() {

    // get an instance of the wrongTable before the schema gets marked to deletion
    $wrongTable = $this->sm->hasSchema(self::DBTEST)->hasTable("wrongTable");

    // drop the schema
    $this->sm->dropSchema(self::DBTEST);

    // check if the schema is marked to be deleted
    $this->assertFalse($this->sm->hasSchema(self::DBTEST),
            "Schema '".self::DBTEST."' was expected to be marked to be deleted, therefore, it's not expected to be found.");

    // tables inside this schema should be deleted too
    $this->assertTrue($wrongTable->shouldDelete(), "'wrongTable' table is expected to be marked to be deleted, but it is not");

    // commits the change
    $this->sm->flush();

    // tables inside this schema should be marked as deleted now
    $this->assertTrue($wrongTable->isDeleted(), "Tables should appear as deleted after a schema gets dropped");
    $this->assertFalse($wrongTable->hasIndex('wrongIdx'), "A index shouldn't show up in the hasIndex, after the schema is dropped");
    $this->assertFalse($wrongTable->hasColumn('wrongAge'), "A column shouldn't show up in the hasColumn, after the schema is dropped");
  }

  public function testImportFromJSONFile() {
    $filePathOrigin = __DIR__ . DIRECTORY_SEPARATOR . "database_example.json";
    $this->sm->loadFromJSONFile($filePathOrigin);

    $this->assertInstanceOf("\PHPSchemaManager\Objects\Schema", $this->sm->hasSchema("Library"));

    // stores the data in the database
    $this->sm->flush();

    $this->assertEquals(\PHPSchemaManager\Objects\Column::NULLVALUE,
            $this->sm->hasSchema("Library")->hasTable("Book")->hasColumn("price")->getDefaultValue());
  }

  public function testUpdate2Schemas() {
    // to make things easier, pre-defined schemas will be used
    $filePathLibrary = __DIR__ . DIRECTORY_SEPARATOR . "schema_library.json";
    $this->sm->loadFromJSONFile($filePathLibrary);

    $filePathInstitution = __DIR__ . DIRECTORY_SEPARATOR . "schema_institution.json";
    $this->sm->loadFromJSONFile($filePathInstitution);

    $reviewTable = new \PHPSchemaManager\Objects\Table('review');

    $columnReviewId = new \PHPSchemaManager\Objects\Column('reviewId');
    $columnReviewId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

    $columnReviewerId = new \PHPSchemaManager\Objects\Column('reviewerId');
    $columnReviewerId->setType(\PHPSchemaManager\Objects\Column::INT);
    $columnReviewerId->setSize(10);

    $columnReview = new \PHPSchemaManager\Objects\Column('Review');
    $columnReview->setType(\PHPSchemaManager\Objects\Column::TEXT);

    $indexReviewerId = new \PHPSchemaManager\Objects\Index('idxReviewerId');

    $reviewTable->addColumn($columnReviewId);
    $reviewTable->addColumn($columnReviewerId);
    $reviewTable->addColumn($columnReview);
    $reviewTable->addIndex($indexReviewerId);

    $this->sm->hasSchema("testLibrary")->addTable($reviewTable);


    $resourceTable = new \PHPSchemaManager\Objects\Table('resource');

    $columnResourceId = new \PHPSchemaManager\Objects\Column('resourceId');
    $columnResourceId->setType(\PHPSchemaManager\Objects\Column::SERIAL);

    $columnResourceName = new \PHPSchemaManager\Objects\Column('name');
    $columnResourceName->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
    $columnResourceName->setSize(100);

    $columnResourceCost = new \PHPSchemaManager\Objects\Column('cost');
    $columnResourceCost->setType(\PHPSchemaManager\Objects\Column::DECIMAL);
    $columnResourceCost->setSize("6,2");

    $indexResourceName = new \PHPSchemaManager\Objects\Index('idxResourceName');

    $resourceTable->addColumn($columnResourceId);
    $resourceTable->addColumn($columnReviewerId);
    $resourceTable->addColumn($columnReview);
    $resourceTable->addIndex($indexResourceName);

    $this->sm->hasSchema("testInstitution")->addTable($resourceTable);

    $this->sm->flush();


    // Now check if the tables where correctly create, by using a new instance
    // to the database
    $m = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);
    $m->setIgnoredSchemas(array('information_schema', 'performance_schema', 'mysql', 'test'));

    $this->assertInstanceOf("\PHPSchemaManager\Objects\Table", $m->hasSchema('testLibrary')->hasTable('review'), "Table 'review' wasn't found in the schema 'testLibrary'");
    $this->assertInstanceOf("\PHPSchemaManager\Objects\Table", $m->hasSchema('testInstitution')->hasTable('resource'), "Table 'resource' wasn't found in the schema 'testInstitution'");
    $this->assertEquals(4, $m->hasSchema("testLibrary")->countTables(), "The Schema 'testLibrary' is expected to have 4 tables");
    $this->assertEquals(3, $m->hasSchema("testInstitution")->countTables(), "The Schema 'testInstitution' is expected to have 4 tables");

    $m->hasSchema("testInstitution")->drop();
    $m->hasSchema("testLibrary")->drop();

    $m->flush();

    $this->assertFalse($m->hasSchema('testLibrary'), "Schema 'testLibrary' should be deleted by now");
    $this->assertFalse($m->hasSchema('testInstitution'), "Schema 'testInstitution' should be deleted by now");
  }

  public function testForeignKeyCreateTable() {

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
      $bookAuthorId->setSize(10);
      $bookAuthorId->unsigned();
      $bookAuthorId->references($authorId);

      $bookTitle = new \PHPSchemaManager\Objects\Column('title');
      $bookTitle->setType(\PHPSchemaManager\Objects\Column::VARCHAR);
      $bookTitle->setSize(250);

      $bookTable = new \PHPSchemaManager\Objects\Table('book');
      $bookTable->addColumn($bookId);
      $bookTable->addColumn($bookAuthorId);
      $bookTable->addColumn($bookTitle);

      $s = $this->sm->createNewSchema("ModernLibrary");

      if ($table = $s->hasTable('book')) {
          $table->drop();
          $s->flush();
      }

      if ($table = $s->hasTable('author')) {
          $table->drop();
          $s->flush();
      }

      $s->addTable($authorTable);
      $s->addTable($bookTable);

      $s->flush();

      // check if the fk was marked as synced
      $this->assertTrue($s->hasTable('book')->hasColumn('authorId')->isSynced());

      // creates a new manager to for the library to read the data from the database
      $ma = \PHPSchemaManager\PHPSchemaManager::getManager($this->conn);
      $m = $ma->hasSchema('ModernLibrary');

      $this->assertTrue($m->hasTable('book')->hasColumn('authorId')->isFK(),
          "The authorId column is expected to be a FK");

      $this->assertInstanceOf('\PHPSchemaManager\Objects\Column',
          $m->hasTable('book')->hasColumn('authorId')->getReferencedColumn(), "Expected to get the Column referenced");

      $this->assertEquals('author',
          $m->hasTable('book')->hasColumn('authorId')->getReferencedColumn()->getFather()->getName(),
          "The referenced column is expected to belong to the 'author' table");

      $s->drop();
  }

  /**
   * @expectedException \PHPSchemaManager\Exceptions\ColumnException
   */
  public function testForeignKeyCreationInconsistency() {
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
      $bookAuthorId->references($authorId);
  }
}
