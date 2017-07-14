<?php

namespace App\Helpers\ExerciseConfig;


class FileArrayVariable extends Variable
{
  public function __construct(VariableMeta $meta) {
    parent::__construct($meta);
  }

  public function getType(): ?string {
    return VariableTypes::$FILE_ARRAY_TYPE;
  }
}
