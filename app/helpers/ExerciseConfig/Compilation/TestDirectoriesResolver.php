<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\MkdirBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\DumpResultsBox;
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
      $variableValue = $outputPort->getVariableValue();
      if ($variableValue && $variableValue->isFile()) {
        $outputPort->getVariableValue()->setValuePrefix($node->getTestId() . ConfigParams::$PATH_DELIM);
      }
    }
  }

  /**
   * Based on given information create mkdir box and node.
   * @param string $testId
   * @return Node
   * @throws ExerciseConfigException
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
   * Based on given information create test dump result box and node.
   * @param string $testId
   * @return Node
   * @throws ExerciseConfigException
   */
  private function createDumpResultsNode(string $testId): Node {
    $variable = new Variable(VariableTypes::$STRING_TYPE);
    $variable->setValue($testId);

    $port = (new Port((new PortMeta)->setType($variable->getType())))->setVariableValue($variable);

    $boxMeta = (new BoxMeta)->setName(DumpResultsBox::$DUMP_RESULTS_TYPE);
    $box = (new DumpResultsBox($boxMeta))->setInputPort($port);

    $node = (new Node)->setBox($box)->setTestId($testId);
    return $node;
  }

  /**
   * Add mkdir tasks for all directories at the beginning of the tree.
   * @param RootedTree $tree
   * @param Node[][] $testNodes indexed with testId
   * @param CompilationParams $params
   * @return RootedTree
   * @throws ExerciseConfigException
   */
  private function addDirectories(RootedTree $tree, array $testNodes,
      CompilationParams $params): RootedTree {
    if (count($testNodes) === 0) {
      return $tree;
    }

    // go through all tests
    $lastMkdirNode = null;
    $result = new RootedTree();
    foreach ($testNodes as $testId => $nodes) {
      if ($lastMkdirNode === null) {
        $lastMkdirNode = $this->createMkdirNode($testId);
        $result->addRootNode($lastMkdirNode);
      } else {
        $mkdirNode = $this->createMkdirNode($testId);
        $mkdirNode->addParent($lastMkdirNode);
        $lastMkdirNode->addChild($mkdirNode);
        $lastMkdirNode = $mkdirNode;
      }

      // set dependencies for all nodes in test
      foreach ($nodes as $node) {
        $node->addDependency($lastMkdirNode);
      }

      if ($params->isDebug()) {
        $dumpResultsNode = $this->createDumpResultsNode($testId);
        $dumpResultsNode->addParent($lastMkdirNode);
        $dumpResultsNode->addDependency($lastMkdirNode);
        $lastMkdirNode->addChild($dumpResultsNode);
        $lastMkdirNode = $dumpResultsNode;
      }
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
   * @param CompilationParams $params
   * @return RootedTree
   * @throws ExerciseConfigException
   */
  public function resolve(RootedTree $tree, CompilationParams $params): RootedTree {
    $testNodes = [];
    $stack = array_reverse($tree->getRootNodes());
    while (!empty($stack)) {
      $current = array_pop($stack);
      $testId = $current->getTestId();
      if ($testId !== null) {
        // all nodes of each tests are saved and further dependencies
        // on mkdir tasks are set on them
        if (!array_key_exists($testId, $testNodes)) {
          $testNodes[$testId] = [];
        }
        $testNodes[$testId][] = $current;
      }

      // process current node
      $this->processNode($current);

      // add children of current node into stack
      foreach (array_reverse($current->getChildren()) as $child) {
        $stack[] = $child;
      }
    }

    return $this->addDirectories($tree, $testNodes, $params);
  }

}
