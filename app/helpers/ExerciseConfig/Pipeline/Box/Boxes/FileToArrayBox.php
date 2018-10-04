<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which converts a file into a single-item array of files.
 */
class FileToArrayBox extends ScalarToArrayBox
{
  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function init() {
    self::$SCALAR_TO_ARRAY_TYPE = "file-to-array";
    self::$DEFAULT_NAME = "File to array";
    static::initScalarToArray(VariableTypes::$FILE_TYPE, VariableTypes::$FILE_ARRAY_TYPE);
  }

}
