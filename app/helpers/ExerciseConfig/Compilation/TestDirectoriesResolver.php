<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\MkdirBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;


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
    if ($node->getTestId() === null) {
      return;
    }

    foreach ($node->getBox()->getOutputPorts() as $outputPort) {
      if ($outputPort->getVariableValue()->isFile()) {
        $outputPort->getVariableValue()->setValuePrefix($node->getTestId() . ConfigParams::$PATH_DELIM);
      }
    }
  }

  /**
   * Based on given information create mkdir box and node.
   * @param string $testId
   * @return Node
   */
  private function createMkdirNode(string $testId): Node {
    $variable = new Variable(VariableTypes::$STRING_TYPE);
    $variable->setValue($testId);

    $port = (new Port((new PortMeta)->setType($variable->getType())))->setVariableValue($variable);

    $boxMeta = (new BoxMeta)->setName(MkdirBox::$MKDIR_TYPE);
    $box = (new MkdirBox($boxMeta))->setInputPort($port);

    $node = (new Node)->setBox($box)->setTestId($testId);
    return $node;
  }

  /**
   * Add mkdir tasks for all directories at the beginning of the tree.
   * @param RootedTree $tree
   * @param array $testIds
   * @return RootedTree
   */
  private function addDirectories(RootedTree $tree, array $testIds): RootedTree {
    $testIds = array_values(array_unique($testIds));
    if (count($testIds) === 0) {
      return $tree;
    }

    $lastMkdirNode = $this->createMkdirNode($testIds[0]);
    $result = new RootedTree();
    $result->addRootNode($lastMkdirNode);

    // go through all tests
    for ($i = 1; $i < count($testIds); $i++) {
      $testId =  $testIds[$i];

      $mkdirNode = $this->createMkdirNode($testId);
      $mkdirNode->addParent($lastMkdirNode);
      $lastMkdirNode->addChild($mkdirNode);

      $lastMkdirNode = $mkdirNode;
    }

    // do not forget to connect original root nodes to new tree
    foreach ($tree->getRootNodes() as $node) {
      $lastMkdirNode->addChild($node);
      $node->addParent($lastMkdirNode);
    }

    return $result;
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
