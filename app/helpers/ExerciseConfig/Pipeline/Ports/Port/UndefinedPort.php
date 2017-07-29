<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Ports;

use App\Helpers\ExerciseConfig\VariableTypes;


class UndefinedPort extends Port
{
  public function __construct(PortMeta $meta) {
    parent::__construct($meta);
  }

  public function getType(): ?string {
    return VariableTypes::$UNDEFINED_TYPE;
  }
}
