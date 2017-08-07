<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Tree;


/**
 * Internal exercise configuration compilation service. Handles optimisation
 * of boxes which are duplicate in multiple tests. Resulting array of boxes
 * might be rearranged and reindexed into multidimensional.
 */
class TestBoxesOptimizer {

  /**
   * Optimize given array of boxes in tests and remove duplicate boxes.
   * Resulting array will be multidimensional sort-of tree.
   * @param Tree[] $tests
   * @return Tree
   */
  public function optimize(array $tests): Tree {
    return new Tree();
  }

}
