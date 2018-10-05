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
 * Internal exercise configuration compilation service. Handles creation of the directories used during execution and
 * creating dumping boxes for all created directories. Alongside that for optimized boxes the name of the directory in
 * which they will be processed has to be found.
 * @note Should be called after optimisation.
 */
class DirectoriesResolver {

  /**
   * Resolve test directory for a single node. Only output ports are processed
   * in all nodes, because output ports should be files which ones are emitted
   * further.
   * @param Node $node
   * @param CompilationContext $context
   */
  private function processNode(Node $node, CompilationContext $context) {
    if ($node->getTestId() === null) {
      return;
    }

    $testName = $context->getTestsNames()[$node->getTestId()];
    foreach ($node->getBox()->getOutputPorts() as $outputPort) {
      $variableValue = $outputPort->getVariableValue();
      if ($variableValue && $variableValue->isFile()) {
        $outputPort->getVariableValue()->setDirectory($testName);
      }
    }
  }

  /**
   * Based on given information create mkdir box and node.
   * @param string $testId
   * @param string $testName
   * @return Node
   * @throws ExerciseConfigException
   */
  private function createMkdirNode(string $testId, string $testName): Node {
    $variable = new Variable(VariableTypes::$STRING_TYPE);
    $variable->setValue($testName);

    $port = (new Port((new PortMeta())->setType($variable->getType())))->setVariableValue($variable);

    $boxMeta = (new BoxMeta())->setName(MkdirBox::$MKDIR_TYPE);
    $box = (new MkdirBox($boxMeta))->setInputPort($port);

    $node = (new Node())->setBox($box)->setTestId($testId);
    return $node;
  }

  /**
   * Based on given information create test dump result box and node.
   * @param string $testId
   * @param string $testName
   * @return Node
   * @throws ExerciseConfigException
   */
  private function createDumpResultsNode(string $testId, string $testName): Node {
    $variable = new Variable(VariableTypes::$STRING_TYPE);
    $variable->setValue($testName);

    $port = (new Port((new PortMeta())->setType($variable->getType())))->setVariableValue($variable);

    $boxMeta = (new BoxMeta())->setName(DumpResultsBox::$DUMP_RESULTS_TYPE);
    $box = (new DumpResultsBox($boxMeta))->setInputPort($port);

    $node = (new Node())->setBox($box)->setTestId($testId);
    return $node;
  }

  /**
   * Add mkdir tasks for all directories at the beginning of the tree.
   * @param RootedTree $tree
   * @param Node[][] $directoriesNodes indexed with testId
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @return RootedTree
   * @throws ExerciseConfigException
   */
  private function addDirectories(RootedTree $tree, array $directoriesNodes,
      CompilationContext $context, CompilationParams $params): RootedTree {
    if (count($directoriesNodes) === 0) {
      return $tree;
    }

    // go through all tests
    $lastMkdirNode = null;
    $result = new RootedTree();
    foreach ($directoriesNodes as $testId => $nodes) {
      $testName = $context->getTestsNames()[$testId];

      if ($lastMkdirNode === null) {
        $lastMkdirNode = $this->createMkdirNode($testId, $testName);
        $result->addRootNode($lastMkdirNode);
      } else {
        $mkdirNode = $this->createMkdirNode($testId, $testName);
        $mkdirNode->addParent($lastMkdirNode);
        $lastMkdirNode->addChild($mkdirNode);
        $lastMkdirNode = $mkdirNode;
      }

      // set dependencies for all nodes in test
      foreach ($nodes as $node) {
        $node->addDependency($lastMkdirNode);
      }

      if ($params->isDebug()) {
        $dumpResultsNode = $this->createDumpResultsNode($testId, $testName);
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
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @return RootedTree
   * @throws ExerciseConfigException
   */
  public function resolve(RootedTree $tree, CompilationContext $context, CompilationParams $params): RootedTree {
    // Let's break it down...
    // DirectoriesResolver works in cooperation with BoxesOptimizer which optimizes the flow of the tasks and marks the
    // nodes which were optimized (this effectively means settings the test-id to null). Directories resolver then
    // goes through the tree and creates the directories needed for execution. If the test-id is set, it is easy and
    // straightforward, if it is not set the directories have to be smartly named and generated.
    // The algorithm follows... The tree is searched with breadth-first approach. Every node is processed in
    // the following way. If the node belongs to the test, the test identification is recorded and children of this node
    // are processed. If the node was optimised (has null test-id) then it is needed further processing. We need to
    // figure out the name of the directory which will be created for this optimized node and its sub-nodes. The name of
    // the directory is composed of categories of boxes in the most left sub-branch which does not have test-id set.
    // Once the name is known, it is used as a directory for the processed node and all the sub-nodes in the most left
    // branch of the tree. After that, children of the node are processed.

    $directoriesNodes = [];
    $stack = array_reverse($tree->getRootNodes());
    while (!empty($stack)) {
      $current = array_pop($stack);
      $testId = $current->getTestId();
      if ($testId !== null) {
        // all nodes of each tests are saved and further dependencies
        // on mkdir tasks are set on them
        if (!array_key_exists($testId, $directoriesNodes)) {
          $directoriesNodes[$testId] = [];
        }
        $directoriesNodes[$testId][] = $current;
      }

      // process current node
      $this->processNode($current, $context);

      // add children of current node into stack
      foreach (array_reverse($current->getChildren()) as $child) {
        $stack[] = $child;
      }
    }

    return $this->addDirectories($tree, $directoriesNodes, $context, $params);
  }

}
