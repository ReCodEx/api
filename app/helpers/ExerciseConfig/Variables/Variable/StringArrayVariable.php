<?php

namespace App\Helpers\ExerciseConfig;


class StringArrayVariable extends Variable
{
  public function __construct(VariableMeta $meta) {
    parent::__construct($meta);
  }

  public function getType(): ?string {
    return VariableTypes::$STRING_ARRAY_TYPE;
  }
}
