<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which converts a string into a single-item array of strings.
 */
class StringToArrayBox extends ScalarToArrayBox
{
  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function init() {
    self::$SCALAR_TO_ARRAY_TYPE = "string-to-array";
    self::$DEFAULT_NAME = "String to array";
    static::initScalarToArray(VariableTypes::$STRING_TYPE, VariableTypes::$STRING_ARRAY_TYPE);
  }

}
