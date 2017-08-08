<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;


/**
 * Internal exercise configuration compilation service. Which is supposed to
 * sort boxes in each test to correspond to proper execution order without
 * conflicts.
 */
class BoxesSorter {

  /**
   * Topological sort of given tree, stack oriented.
   * @param MergeTree $mergeTree
   * @return array
   * @throws ExerciseConfigException
   */
  private function topologicalSort(MergeTree $mergeTree): array {
    // Stack will hold pair of values, first one will be in-processing flag,
    // second one actual node of tree. in-processing flag is used to detect
    // cycles in the tree
    $stack = array();
    $queue = array();

    // fill initial values in the stack, last element is the top of the stack
    foreach (array_merge($mergeTree->getOutputNodes(),
      $mergeTree->getOtherNodes(), $mergeTree->getInputNodes()) as $node) {
      $stack[] = array(false, $node);
    }

    // main topological sorting loop
    while(!empty($stack)) {
      $stackElement = array_pop($stack);
      $inProcessing = $stackElement[0];
      $node = $stackElement[1];

      // all children of node were successfully processed... finish it
      if ($inProcessing) {
        $node->setFinished(true);
        $queue[] = $node;
        continue;
      }

      // node was visited, but it is not finished and not in processing
      // --> cycle detected
      if ($node->isVisited()) {
        if ($node->isFinised()) {
          continue;
        }

        throw new ExerciseConfigException("Cycle in tree detected in node {$node->getBox()->getName()}.");
      }

      // visit current node
      $node->setVisited(true);
      // do not forget to re-stack current node with in-processing flag
      $stack[] = array(true, $node);

      // process all children and stack them
      foreach ($node->getChildren() as $child) {
        $stack[] = array(false, $child);
      }
    }

    return array_reverse($queue);
  }

  /**
   * Sort tree and return newly created rooted tree.
   * @param MergeTree $mergeTree
   * @return RootedTree
   * @throws ExerciseConfigException
   */
  private function sortTree(MergeTree $mergeTree): RootedTree {
    $sorted = $this->topologicalSort($mergeTree);

    // initialize rooted tree and its root
    $tree = new RootedTree();
    $tree->addRootNode($sorted[0]);

    // make de-facto linked list of nodes
    $previous = $sorted[0];
    for ($i = 1; $i < count($sorted); $i++) {
      $current = $sorted[$i];

      // remove old connections
      $previous->setChildren(array());
      $current->setParents(array());

      // ... and create new ones
      $previous->addChild($current);
      $current->addParent($previous);
    }

    return $tree;
  }

  /**
   * For each test sort its boxes to order which makes execution sense.
   * @param MergeTree[] $tests
   * @return RootedTree[]
   */
  public function sort(array $tests): array {

    // go through all tests and create rooted trees for them
    $result = array();
    foreach ($tests as $mergeTree) {
      $result[] = $this->sortTree($mergeTree);
    }

    return $result;
  }

}
