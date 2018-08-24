<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;

/**
 * Internal exercise configuration compilation service. Handles optimisation
 * of boxes which are duplicate in multiple tests. Result of this process is
 * rooted tree which might have multiple roots. All nodes should have set
 * test identification from the past, if some node is merged and optimised
 * identification of test should be cleared.
 */
class BoxesOptimizer {

  /**
   * Determine if given ports can be optimized or not.
   * @param Port $first could not be null
   * @param Port $second could not be null
   * @return bool
   */
  private static function canPortsBeOptimized(Port $first, Port $second) {
    $variableValue = $first->getVariableValue();
    $otherVariableValue = $second->getVariableValue();
    if ($variableValue === null && $otherVariableValue === null) {
      return true;
    }

    if ($variableValue === null || $otherVariableValue === null ||
      $variableValue->getValue() !== $variableValue->getValue()) {
      return false;
    }

    return true;
  }

  /**
   * Compare two given nodes and determine if they can be optimized.
   * @param Node $first
   * @param Node $second
   * @return bool
   */
  private static function canNodesBeOptimized(Node $first, Node $second): bool {
    if ($first->getBox() === null || $second->getBox() === null) {
      return false;
    }

    $firstBox = $first->getBox();
    $secondBox = $second->getBox();

    // check box types
    if ($firstBox->getType() !== $secondBox->getType()) {
      return false;
    }

    // check ports counts
    if (count($firstBox->getInputPorts()) !== count($secondBox->getInputPorts()) ||
      count($firstBox->getOutputPorts()) !== count($secondBox->getOutputPorts())) {
      return false;
    }

    // check input ports for equality
    foreach ($firstBox->getInputPorts() as $port) {
      $otherPort = $secondBox->getInputPort($port->getName());
      if ($otherPort === null ||
        self::canPortsBeOptimized($port, $otherPort) === false) {
        return false;
      }
    }

    // check output ports for equality
    foreach ($firstBox->getOutputPorts() as $port) {
      $otherPort = $secondBox->getOutputPort($port->getName());
      if ($otherPort === null ||
        self::canPortsBeOptimized($port, $otherPort) === false) {
        return false;
      }
    }

    return true;
  }

  /**
   * In-place optimisation of the given tree.
   * @param Node $rootNode
   */
  private function optimizeTree(Node $rootNode) {
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
    // Therefore this heuristics is capable only optimise the beginning of the
    // trees and not the ends. This is generally fine for our usage, because
    // usually the same thing for all tests is compilation which is the first
    // set of tasks in the tree.

    // processing queue simulating breadth-first search
    $queue = [$rootNode];
    while (!empty($queue)) {
      $node = array_shift($queue); /** @var Node $node */
      $children = $node->getChildren();

      if (count($children) < 2) {
        // there is nothing to optimize if node has only one or none children
        continue;
      }

      // go through all children from currently processed node
      // all children are taken as a base for further comparisons
      while (!empty($children)) {
        $child = array_shift($children);
        $compChildren = $children;

        // base $child has to be compared against all other children
        // if the children are equal, then they should be optimised
        while(!empty($compChildren)) {
          $compared = array_shift($compChildren);

          // children can be optimized... so do it
          if (self::canNodesBeOptimized($child, $compared)) {
            // delete the children from the base children array
            $children = array_diff($children, [$compared]);
            $child->setTestId(null); // clear the test identification
            // TODO: set directory

            foreach ($compared->getChildren() as $comparedChild) {
              $comparedChild->removeParent($compared);
              $comparedChild->addParent($child);
              $child->addChild($comparedChild);
            }

            foreach ($compared->getParents() as $comparedParent) {
              $comparedParent->removeChild($compared);
              $comparedParent->addChild($child);
              $child->addParent($comparedParent);
            }
          }
        }
      }

      // do not forget to add newly created children from current node to
      // processing queue
      array_merge($queue, $node->getChildren());
    }
  }

  /**
   * Optimize given array of boxes in tests and remove duplicate boxes.
   * The optimizer should return RootedTree which should be similar to given
   * trees.
   * @param RootedTree[] $tests
   * @return RootedTree
   */
  public function optimize(array $tests): RootedTree {
    // create new root node which contains all root nodes from given subtrees
    $rootNode = new Node();
    foreach ($tests as $testName => $test) {
      foreach ($test->getRootNodes() as $node) {
        $rootNode->addChild($node);
      }
    }

    // The hell of optimisation awaits...
    // Forth, and fear no darkness! Arise! Arise, Riders of ReCodEx! Spears
    // shall be shaken, shields shall be splintered! A sword day... a red day...
    // ere the sun rises!
    // Optimize! Optimize! OPTIMIZE!

    $this->optimizeTree($rootNode);

    // based on created root node create a rooted tree, root node was only
    // temporary and therefore we can use only its children
    $tree = new RootedTree();
    foreach ($rootNode->getChildren() as $node) {
      $tree->addRootNode($node);
    }
    return $tree;
  }

}
