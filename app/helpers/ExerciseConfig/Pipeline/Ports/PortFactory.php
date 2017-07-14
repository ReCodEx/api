<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Ports;


use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\VariableTypes;

class PortFactory
{
  /**
   * Based on given meta information construct proper variable type.
   * @param PortMeta $meta
   * @return Port
   * @throws ExerciseConfigException
   */
  public function create(PortMeta $meta): Port {
    if (strtolower($meta->getType()) === strtolower(VariableTypes::$FILE_ARRAY_TYPE)) {
      return new FileArrayPort($meta);
    } else if (strtolower($meta->getType()) === strtolower(VariableTypes::$FILE_TYPE)) {
      return new FilePort($meta);
    } else if (strtolower($meta->getType()) === strtolower(VariableTypes::$STRING_ARRAY_TYPE)) {
      return new StringArrayPort($meta);
    } else if (strtolower($meta->getType()) === strtolower(VariableTypes::$STRING_TYPE)) {
      return new StringPort($meta);
    } else {
      throw new ExerciseConfigException("Unknown type: {$meta->getType()}");
    }
  }
}
