<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\BoxCategories;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which will take two file arrays on its input and join them to one merged
 * array.
 */
class MergeFilesBox extends MergeBox
{
  public static $BOX_TYPE = "merge-files";
  public static $DEFAULT_NAME = "Merge files";

  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function init() {
    static::initMerger(VariableTypes::$FILE_ARRAY_TYPE);
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
