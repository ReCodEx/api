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
   * In-place optimisation of the given tree.
   * @param RootedTree $tree
   */
  private function optimizeTree(RootedTree $tree) {
    // Ok, here it goes...
    // The whole optimisation is based on following heuristic. We were given a
    // rooted tree which can have multiple root nodes. The tree is traversed by
    // levels and can be implemented with recursion. At first all root nodes are
    // compared if there are any duplicates. If duplicates are found, then these
    // nodes are merged into one. The next step is to process all subtrees which
    // were created. Thus if there were 4 nodes and 2 and 2 are the same, these
    // four nodes are contracted into 2 nodes. These two nodes then contain
    // subtrees from the 2 nodes of which they are composed. After this,
    // the procedure is repeated for all nodes from subtrees.
    // Therefore this heuristics is capable only optimise the begging of the
    // trees and not the ends. This is generally fine for our usage, because
    // usually the same thing for all tests is compilation which is the first
    // set of tasks in the tree.

    // TODO: implementation
  }

  /**
   * Optimize given array of boxes in tests and remove duplicate boxes.
   * The optimizer should return RootedTree which should be similar to given
   * trees.
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

    // The hell of optimisation awaits...
    // Forth, and fear no darkness! Arise! Arise, Riders of ReCodEx! Spears
    // shall be shaken, shields shall be splintered! A sword day... a red day...
    // ere the sun rises!
    // Optimize! Optimize! OPTIMIZE!

    $this->optimizeTree($tree);
    return $tree;
  }

}
