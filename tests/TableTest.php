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
  
}
