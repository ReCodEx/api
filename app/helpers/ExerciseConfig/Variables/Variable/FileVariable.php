<?php

namespace App\Helpers\ExerciseConfig;


class FileVariable extends Variable
{
  public function __construct(VariableMeta $meta) {
    parent::__construct($meta);
  }

  public function getType(): ?string {
    return VariableTypes::$FILE_TYPE;
  }

  public function isArray(): bool {
    return false;
  }

}
