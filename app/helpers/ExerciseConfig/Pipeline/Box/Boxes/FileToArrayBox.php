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
  public static $BOX_TYPE = "file-to-array";
  public static $DEFAULT_NAME = "File to array";

  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function init() {
    static::initScalarToArray(VariableTypes::$FILE_TYPE, VariableTypes::$FILE_ARRAY_TYPE);
  }

  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$BOX_TYPE;
  }

  /**
   * Get default name of this box.
   * @return string
   */
  public function getDefaultName(): string {
    return self::$DEFAULT_NAME;
  }

}
