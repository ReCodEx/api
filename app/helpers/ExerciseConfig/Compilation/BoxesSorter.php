<?php

namespace App\Helpers\ExerciseConfig\Compilation;


/**
 * Internal exercise configuration compilation service. Which is supposed to
 * sort boxes in each test to correspond to proper execution order without
 * conflicts.
 */
class BoxesSorter {

  /**
   * For each test sort its boxes to order which makes execution sense.
   * @param array $tests
   * @return array
   */
  public function sort(array $tests): array {
    return array();
  }

}
