<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;


use App\Exceptions\ExerciseConfigException;

class BoxFactory
{
  public static $DATA_TYPE = "data";

  /**
   * Based on given meta information construct proper box type.
   * @param BoxMeta $meta
   * @return Box
   * @throws ExerciseConfigException
   */
  public function create(BoxMeta $meta): Box {
    if (strtolower($meta->getType()) === strtolower(self::$DATA_TYPE)) {
      return new DataBox($meta);
    } else {
      throw new ExerciseConfigException("Unknown type: {$meta->getType()}");
    }
  }
}
