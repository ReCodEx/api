<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseCompilationException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;


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
   * @throws ExerciseCompilationException
   */
  private function topologicalSort(MergeTree $mergeTree): array {
    // Okey, let's make this crystal clear...
    // This is not ordinary topological sorting algorithm, it is based on a bit reversed idea and adds priority nodes
    // which will be processed beforehand. This is done because of optimisation which needs a specific order of given
    // nodes. It is recommended to have input type nodes close to its children, therefore not having input nodes
    // at the beginning of outputted nodes order but close to their actual usage. The algorithm of sorting takes this
    // into account by reversing the tree search (going from bottom to up of the tree) and having priorities when adding
    // parents into execution stack. Nodes of types other then input ones are preferred and processed earlier, which
    // effectively means having input nodes closer to their children.

    // Stack will hold pair of values, first one will be in-processing flag,
    // second one actual node of tree. in-processing flag is used to detect
    // cycles in the tree
    $stack = array();
    $queue = array();

    // fill initial values in the stack, last element is the top of the stack
    // reverse is here for the purpose of preferred order in initial stack
    foreach ($mergeTree->getAllReversedNodes() as $node) {
      $stack[] = array(false, $node);
    }

    // main topological sorting loop
    while(!empty($stack)) {
      $stackElement = array_pop($stack);
      $inProcessing = $stackElement[0];
      $node = $stackElement[1]; /** @var PortNode $node */

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

        throw new ExerciseCompilationException("Cycle in tree detected in node '{$node->getBox()->getName()}'.");
      }

      // visit current node
      $node->setVisited(true);
      // do not forget to re-stack current node with in-processing flag
      $stack[] = array(true, $node);

      // process all parents, if the parent is input node add it to stack instantly, otherwise the parent should be
      // added later, effectively being processed earlier
      $nodesToAdd = [];
      foreach ($node->getParents() as $parent) {
        if ($parent->getBox() instanceof DataInBox) {
          $stack[] = array(false, $parent);
        } else {
          $nodesToAdd[] = array(false, $parent);
        }
      }
      $stack = array_merge($stack, $nodesToAdd);
    }

    return $queue;
  }

  /**
   * Sort tree and return newly created rooted tree.
   * @param MergeTree $mergeTree
   * @return RootedTree
   * @throws ExerciseCompilationException
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
        $index = array_search($parent, $sorted, true);
        if ($index === false) {
          throw new ExerciseCompilationException("Malformed internal compilation structure. PortNode not found.");
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
   * @throws ExerciseCompilationException
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
