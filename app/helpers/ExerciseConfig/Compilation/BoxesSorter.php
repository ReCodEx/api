<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Tree;


/**
 * Internal exercise configuration compilation service. Which is supposed to
 * sort boxes in each test to correspond to proper execution order without
 * conflicts.
 */
class BoxesSorter {

  /**
   * For each test sort its boxes to order which makes execution sense.
   * @param Tree[] $tests
   * @return array
   */
  public function sort(array $tests): array {
    return array();
  }

}
