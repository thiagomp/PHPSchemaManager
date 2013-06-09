<?php
namespace PHPSchemaManager\Objects;

interface ObjectEventsInterface
{
    public function onDelete();
    public function onDestroy();
}
