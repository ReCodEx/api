<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;


/**
 * Internal exercise configuration compilation service. This one is supposed
 * to resolve references to variables and fill them directly in ports in boxes.
 * This way next compilation services can compare boxes or directly assign
 * variable values during boxes compilation.
 */
class VariablesResolver {

  /**
   * Go through given array and resolve variables in boxes.
   * @param MergeTree[] $tests
   * @return MergeTree[]
   */
  public function resolve(array $tests): array {
    return $tests;
  }

}
