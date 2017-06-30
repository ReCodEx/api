<?php

namespace App\Helpers\ExerciseConfig;


use App\Exceptions\ExerciseConfigException;

class VariableFactory
{
  /**
   * Based on given meta information construct proper variable type.
   * @param VariableMeta $meta
   * @return Variable
   * @throws ExerciseConfigException
   */
  public function create(VariableMeta $meta): Variable {
    if (strtolower($meta->getType()) === strtolower(Variable::$FILE_ARRAY_TYPE)) {
      return new FileArrayVariable($meta);
    } else if (strtolower($meta->getType()) === strtolower(Variable::$FILE_TYPE)) {
      return new FileVariable($meta);
    } else if (strtolower($meta->getType()) === strtolower(Variable::$STRING_ARRAY_TYPE)) {
      return new StringArrayVariable($meta);
    } else if (strtolower($meta->getType()) === strtolower(Variable::$STRING_TYPE)) {
      return new StringVariable($meta);
    } else {
      throw new ExerciseConfigException("Unknown type: {$meta->getType()}");
    }
  }
}
