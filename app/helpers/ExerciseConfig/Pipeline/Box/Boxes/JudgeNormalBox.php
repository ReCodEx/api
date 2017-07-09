<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;


/**
 * Box which represents recodex-judge-normal executable.
 */
class JudgeNormalBox extends Box
{
  /**
   * JudgeNormalBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }


  public function validateMetadata() {
    // TODO: Implement validateMetadata() method.
  }

  public function fillDefaults() {
    // TODO: Implement fillDefaults() method.
  }

}
