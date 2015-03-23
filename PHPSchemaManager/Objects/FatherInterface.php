<?php
namespace PHPSchemaManager\Objects;

/**
 * Description of iListener
 *
 * @author thiago
 */
interface FatherInterface
{
    public function informChange();
    public function informDeletion(Objects $object);
    public function informSynced();
}
