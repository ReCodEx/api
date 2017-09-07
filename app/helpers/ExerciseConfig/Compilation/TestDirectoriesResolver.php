<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;


/**
 * Internal exercise configuration compilation service. Handles tests in
 * execution and their separation into appropriate sub-directories. This mainly
 * means modification of file variables and prefixing them with proper
 * directory. Mkdir tasks will be also constructed and added to resulting tree.
 * @note Should be called after optimisation.
 */
class TestDirectoriesResolver {

  /**
   * Resolve test directory for a single node. Only output ports are processed
   * in all nodes, because output ports should be files which ones are
   * @param Node $node
   */
  private function processNode(Node $node) {
    // @todo
  }

  /**
   * @param RootedTree $tree
   * @param array $testIds
   * @return RootedTree
   */
  private function addDirectories(RootedTree $tree, array $testIds): RootedTree {
    return $tree; // @todo
  }

  /**
   * Resolve and assign proper directories to particular tests.
   * @param RootedTree $tree
   * @return RootedTree
   */
  public function resolve(RootedTree $tree): RootedTree {
    $testIds = [];
    $stack = array_reverse($tree->getRootNodes());
    while (!empty($stack)) {
      $current = array_pop($stack);
      if ($current->getTestId()) {
        $testIds[] = $current->getTestId();
      }

      // process current node
      $this->processNode($current);

      // add children of current node into stack
      foreach (array_reverse($current->getChildren()) as $child) {
        $stack[] = $child;
      }
    }

    return $this->addDirectories($tree, $testIds);
  }

}
