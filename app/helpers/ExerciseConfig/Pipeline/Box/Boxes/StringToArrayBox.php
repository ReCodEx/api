<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which converts a string into a single-item array of strings.
 */
class StringToArrayBox extends ScalarToArrayBox
{
  public static $BOX_TYPE = "string-to-array";
  public static $DEFAULT_NAME = "String to array";

  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function init() {
    static::initScalarToArray(VariableTypes::$STRING_TYPE, VariableTypes::$STRING_ARRAY_TYPE);
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
