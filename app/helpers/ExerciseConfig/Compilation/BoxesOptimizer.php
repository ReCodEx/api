<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;


/**
 * Internal exercise configuration compilation service. Handles optimisation
 * of boxes which are duplicate in multiple tests. Result of this process is
 * rooted tree which might have multiple roots. All nodes should have set
 * test identification from the past, if some node is merged and optimised
 * identification of test should be cleared.
 */
class BoxesOptimizer {

  /**
   * Optimize given array of boxes in tests and remove duplicate boxes.
   * Resulting array will be multidimensional sort-of tree.
   * @param RootedTree[] $tests
   * @return RootedTree
   */
  public function optimize(array $tests): RootedTree {
    $tree = new RootedTree();
    foreach ($tests as $testName => $test) {
      foreach ($test->getRootNodes() as $rootNode) {
        $tree->addRootNode($rootNode);
      }
    }
    return $tree;
  }

}
