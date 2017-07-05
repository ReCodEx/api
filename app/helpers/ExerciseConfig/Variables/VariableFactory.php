<?php

namespace App\Helpers\ExerciseConfig;


use App\Exceptions\ExerciseConfigException;

class VariableFactory
{
  public static $FILE_TYPE = "file";
  public static $FILE_ARRAY_TYPE = "file[]";
  public static $STRING_TYPE = "string";
  public static $STRING_ARRAY_TYPE = "string[]";

  /**
   * Based on given meta information construct proper variable type.
   * @param VariableMeta $meta
   * @return Variable
   * @throws ExerciseConfigException
   */
  public function create(VariableMeta $meta): Variable {
    if (strtolower($meta->getType()) === strtolower(self::$FILE_ARRAY_TYPE)) {
      return new FileArrayVariable($meta);
    } else if (strtolower($meta->getType()) === strtolower(self::$FILE_TYPE)) {
      return new FileVariable($meta);
    } else if (strtolower($meta->getType()) === strtolower(self::$STRING_ARRAY_TYPE)) {
      return new StringArrayVariable($meta);
    } else if (strtolower($meta->getType()) === strtolower(self::$STRING_TYPE)) {
      return new StringVariable($meta);
    } else {
      throw new ExerciseConfigException("Unknown type: {$meta->getType()}");
    }
  }
}
