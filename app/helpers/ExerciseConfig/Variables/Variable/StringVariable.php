<?php

namespace App\Helpers\ExerciseConfig;


class StringVariable extends Variable
{
  public function __construct(VariableMeta $meta) {
    parent::__construct($meta);
  }

  public function getType(): ?string {
    return VariableTypes::$STRING_TYPE;
  }

  public function isArray(): bool {
    return false;
  }

}
