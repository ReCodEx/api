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
  public static $DATA_TYPE = "data";
  public static $JUDGE_NORMAL_TYPE = "judge-normal";
  public static $GCC_TYPE = "gcc";
  public static $ELF_EXEC_TYPE = "elf-exec";


  /**
   * Associative array indexed by type identifier and containing
   * identifications of box classes.
   * @var array
   */
  private $boxes;

  public function __construct() {
    $this->boxes = [
      self::$DATA_TYPE => DataBox::class,
      self::$JUDGE_NORMAL_TYPE => JudgeNormalBox::class,
      self::$GCC_TYPE => GccCompilationBox::class,
      self::$ELF_EXEC_TYPE => ElfExecutionBox::class
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
