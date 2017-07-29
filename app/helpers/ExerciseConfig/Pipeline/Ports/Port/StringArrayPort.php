<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Ports;

use App\Helpers\ExerciseConfig\VariableTypes;


class StringArrayPort extends Port
{
  public function __construct(PortMeta $meta) {
    parent::__construct($meta);
  }

  public function getType(): ?string {
    return VariableTypes::$STRING_ARRAY_TYPE;
  }
}
