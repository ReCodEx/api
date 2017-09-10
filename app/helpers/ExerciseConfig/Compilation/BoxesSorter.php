<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;


/**
 * Internal exercise configuration compilation service. Which is supposed to
 * sort boxes in each test to correspond to proper execution order without
 * conflicts.
 * @note State of nodes in given trees can and will be changed!
 */
class BoxesSorter {

  /**
   * Topological sort of given tree, stack oriented.
   * @param MergeTree $mergeTree
   * @return PortNode[]
   * @throws ExerciseConfigException
   */
  private function topologicalSort(MergeTree $mergeTree): array {
    // Stack will hold pair of values, first one will be in-processing flag,
    // second one actual node of tree. in-processing flag is used to detect
    // cycles in the tree
    $stack = array();
    $queue = array();

    // fill initial values in the stack, last element is the top of the stack
    // reverse is here for the purpose of preferred order in initial stack
    foreach (array_reverse($mergeTree->getAllNodes()) as $node) {
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
        if ($node->isFinished()) {
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

    // empty tree does not have to be processed
    if (count($mergeTree->getAllNodes()) === 0) {
      return new RootedTree();
    }

    // make topological sort
    $sorted = $this->topologicalSort($mergeTree);
    $nodes = [];

    // initialize rooted tree and its root
    $tree = new RootedTree();
    $previous = new Node($sorted[0]);
    $nodes[] = $previous;
    $tree->addRootNode($previous);

    // make de-facto linked list of nodes
    for ($i = 1; $i < count($sorted); $i++) {
      $current = new Node($sorted[$i]);

      // ... create connections
      $previous->addChild($current);
      $current->addParent($previous);

      // find and assign dependencies
      foreach ($sorted[$i]->getParents() as $parent) {
        $index = array_search($parent, $sorted);
        if ($index === false) {
          throw new ExerciseConfigException("Malformed internal compilation structure. PortNode not found.");
        }
        $current->addDependency($nodes[$index]);
      }

      // do not forget to set previous
      $nodes[] = $current;
      $previous = $current;
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
