<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;


use App\Exceptions\ExerciseConfigException;

class BoxService
{
  public static $DATA_TYPE = "data";
  public static $JUDGE_NORMAL_TYPE = "judge-normal";


  /**
   * Associative array indexed by type identifier and containing
   * identifications of box classes.
   * @var array
   */
  private $boxes;

  public function __construct() {
    $this->boxes = [
      self::$DATA_TYPE => DataBox::class,
      self::$JUDGE_NORMAL_TYPE => JudgeNormalBox::class
    ];
  }

  /**
   * Return array of newly created instances of all available boxes.
   * @return array
   */
  public function getAllBoxes(): array {
    // @todo
  }

  /**
   * Based on given meta information construct proper box type.
   * @param BoxMeta $meta
   * @return Box
   * @throws ExerciseConfigException
   */
  public function create(BoxMeta $meta): Box {

    foreach ($this->boxes as $boxId => $boxClass) {
      if (strtolower($meta->getType()) === strtolower($boxId)) {
        return new $boxClass($meta);
      }
    }

    throw new ExerciseConfigException("Unknown type: {$meta->getType()}");
  }
}
