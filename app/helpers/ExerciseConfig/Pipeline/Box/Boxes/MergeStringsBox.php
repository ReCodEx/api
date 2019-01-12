<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which will take two string arrays on its input and join them to one merged
 * array.
 */
class MergeStringsBox extends Box
{
  use MergeBox;

  public static $BOX_TYPE = "merge-strings";
  public static $DEFAULT_NAME = "Merge strings";

  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function init() {
      static::initMerger(VariableTypes::$STRING_ARRAY_TYPE);
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
