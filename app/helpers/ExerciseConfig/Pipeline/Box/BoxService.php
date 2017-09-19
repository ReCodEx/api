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
      FileInBox::$FILE_IN_TYPE => FileInBox::class,
      FilesInBox::$FILES_IN_TYPE => FilesInBox::class,
      FileOutBox::$FILE_OUT_TYPE => FileOutBox::class,
      FilesOutBox::$FILES_OUT_TYPE => FilesOutBox::class,
      JudgeNormalBox::$JUDGE_NORMAL_TYPE => JudgeNormalBox::class,
      GccCompilationBox::$GCC_TYPE => GccCompilationBox::class,
      GppCompilationBox::$GPP_TYPE => GppCompilationBox::class,
      ElfExecutionBox::$ELF_EXEC_TYPE => ElfExecutionBox::class,
      FpcCompilationBox::$FPC_TYPE => FpcCompilationBox::class,
      McsCompilationBox::$MCS_TYPE => McsCompilationBox::class,
      MonoExecutionBox::$MONO_EXEC_TYPE => MonoExecutionBox::class,
      FetchFilesBox::$FETCH_TYPE => FetchFilesBox::class,
      FetchFileBox::$FETCH_TYPE => FetchFileBox::class,
      JavaRunBox::$JAVA_RUNNER_TYPE => JavaRunBox::class,
      Python3Box::$PYTHON3_TYPE => Python3Box::class
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
