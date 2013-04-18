<?php

namespace PHPSchemaManager\Objects;

interface iObjectEvents {
  public function onDelete();
  public function onDestroy();
}