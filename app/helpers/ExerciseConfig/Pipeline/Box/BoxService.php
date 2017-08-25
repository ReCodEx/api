<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;


/**
 * Box service takes care about all available boxes in system. It works as the
 * factory for default and also config-loaded boxes. Both factory methods are
 * generic and the actual list of all boxes is created during construction. That
 * means all work which has to be done if adding new box is add it as another
 * array field in constructor of this class.
 */
class BoxService
{

  /**
   * Associative array indexed by type identifier and containing
   * identifications of box classes.
   * @var array
   */
  private $boxes;

  /**
   * BoxService constructor.
   */
  public function __construct() {
    $this->boxes = [
      DataInBox::$DATA_IN_TYPE => DataInBox::class,
      DataOutBox::$DATA_OUT_TYPE => DataOutBox::class,
      JudgeNormalBox::$JUDGE_NORMAL_TYPE => JudgeNormalBox::class,
      GccCompilationBox::$GCC_TYPE => GccCompilationBox::class,
      ElfExecutionBox::$ELF_EXEC_TYPE => ElfExecutionBox::class
    ];
  }

  /**
   * Return array of newly created instances of all available boxes with filled
   * default values
   * @return array
   */
  public function getAllBoxes(): array {
    $boxes = array();
    foreach ($this->boxes as $boxClass) {
      $box = new $boxClass(new BoxMeta);
      $box->fillDefaults();
      $boxes[] = $box;
    }

    return $boxes;
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
        $box = new $boxClass($meta);
        $box->validateMetadata();
        return $box;
      }
    }

    throw new ExerciseConfigException("Unknown type: {$meta->getType()}");
  }
}
