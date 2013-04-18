<?php
namespace PHPSchemaManager\Objects;

/**
 * Description of ColumnTypeStrategySerial
 *
 * @author thiago
 */
class ColumnTypeStrategySerial
  implements iColumnTypeStrategy {
  
  protected $column;
  
  function __construct(Column $column) {
    $this->column = $column;
  }
  
  public function configure() {
    $this->column->forbidsNull();
    $this->column->unsigned();
    $this->column->setDefaultValue("");
  }
}
