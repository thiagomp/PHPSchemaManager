<?php
namespace PHPSchemaManager\Objects;

/**
 * Description of iListener
 *
 * @author thiago
 */
interface iFather {
  public function informChange();
  public function informDeletion(Objects $object);
}
