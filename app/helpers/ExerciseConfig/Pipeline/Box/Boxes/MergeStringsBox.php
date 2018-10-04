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
class MergeStringsBox extends MergeBox
{
  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function init() {
      self::$MERGE_TYPE = "merge-strings";
      self::$DEFAULT_NAME = "Merge strings";
      static::initMerger(VariableTypes::$STRING_ARRAY_TYPE);
  }

}
