<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\CopyFileBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\CopyFilesBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\MkdirBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\DumpResultsBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Nette\Utils\Random;


/**
 * Internal exercise configuration compilation service. Handles creation of the directories used during execution and
 * creating dumping boxes for all created directories. Alongside that for optimized boxes the name of the directory in
 * which they will be processed has to be found.
 * @note Should be called after optimisation.
 */
class DirectoriesResolver {

  /**
   * Process single node in context of directories resolver.
   * Basically we need to track directories which should be created.
   * @param Node $node
   * @param string $directoryName
   * @param array $directoriesNodes
   */
  private function processNode(Node $node, string $directoryName, array &$directoriesNodes) {
    // set the directory to the current box in node
    if ($node->getBox()) {
      $node->getBox()->setDirectory($directoryName);
    }

    // all nodes for each directory are saved and further dependencies
    // on mkdir tasks are set on them
    if (!array_key_exists($directoryName, $directoriesNodes)) {
      $directoriesNodes[$directoryName] = [];
    }
    $directoriesNodes[$directoryName][] = $node;

    // set directory on variable, this is used during creation of copy tasks
    foreach ($node->getBox()->getOutputPorts() as $outputPort) {
      $variableValue = $outputPort->getVariableValue();
      if ($variableValue && $variableValue->isFile()) {
        $outputPort->getVariableValue()->setDirectory($directoryName);
      }
    }
  }

  /**
   * Go through the tree from current node and determine the name of the directory in which the node should be
   * executed in. The name is composed of categories of the boxes.
   * @param Node $current
   * @return string
   */
  private function findOptimizedNodesDirectory(Node $current): string {
    $name = "";
    while ($current->getTestId() === null) {
      $category = $current->getBox() ? $current->getBox()->getCategory() : "";
      $name .= !empty($category) ? $category . "_" : "";

      if (count($current->getChildren()) < 1) {
        break;
      }
      $current = current($current->getChildren());
    }

    return $name . Random::generate(10);
  }

  /**
   * Based on given information create mkdir box and node.
   * @param string $directory
   * @return Node
   * @throws ExerciseConfigException
   */
  private function createMkdirNode(string $directory): Node {
    $variable = new Variable(VariableTypes::$STRING_TYPE);
    $variable->setValue($directory);

    $port = (new Port((new PortMeta())->setType($variable->getType())))->setVariableValue($variable);

    $boxMeta = (new BoxMeta())->setName(MkdirBox::$MKDIR_TYPE);
    $box = (new MkdirBox($boxMeta))->setInputPort($port)->setDirectory($directory);

    return (new Node())->setBox($box);
  }

  /**
   * Based on given information create test dump result box and node.
   * @param string $directory
   * @return Node
   * @throws ExerciseConfigException
   */
  private function createDumpResultsNode(string $directory): Node {
    $variable = new Variable(VariableTypes::$STRING_TYPE);
    $variable->setValue($directory);

    $port = (new Port((new PortMeta())->setType($variable->getType())))->setVariableValue($variable);

    $boxMeta = (new BoxMeta())->setName(DumpResultsBox::$DUMP_RESULTS_TYPE);
    $box = (new DumpResultsBox($boxMeta))->setInputPort($port)->setDirectory($directory);

    return (new Node())->setBox($box);
  }

  /**
   * Based on given information create copy box and node.
   * @param Variable $variable
   * @param string $newDirectory
   * @return Node
   * @throws ExerciseConfigException
   */
  private function createCopyNode(Variable $variable, string $newDirectory): Node {
    $outVariable = new Variable($variable->getType());
    $outVariable->setDirectory($newDirectory);
    $outVariable->setValue($variable->getValue());

    $inputPort = (new Port((new PortMeta())->setType($variable->getType())))->setVariableValue($variable);
    $outputPort = (new Port((new PortMeta())->setType($outVariable->getType())))->setVariableValue($outVariable);

    if ($variable->isArray()) {
      $boxMeta = (new BoxMeta())->setName(CopyFilesBox::$COPY_TYPE);
      $box = (new CopyFilesBox($boxMeta))->setInputPort($inputPort)->setOutputPort($outputPort)->setDirectory($newDirectory);
    } else {
      $boxMeta = (new BoxMeta())->setName(CopyFileBox::$COPY_TYPE);
      $box = (new CopyFileBox($boxMeta))->setInputPort($inputPort)->setOutputPort($outputPort)->setDirectory($newDirectory);
    }

    return (new Node())->setBox($box);
  }

  /**
   * Add mkdir tasks for all directories at the beginning of the tree.
   * @param RootedTree $tree
   * @param Node[][] $directoriesNodes indexed with testId
   * @param CompilationParams $params
   * @return RootedTree
   * @throws ExerciseConfigException
   */
  private function addDirectories(RootedTree $tree, array $directoriesNodes,
      CompilationParams $params): RootedTree {
    if (count($directoriesNodes) === 0) {
      return $tree;
    }

    // go through all tests
    $lastMkdirNode = null;
    $result = new RootedTree();
    foreach ($directoriesNodes as $directoryName => $nodes) {
      if ($lastMkdirNode === null) {
        $lastMkdirNode = $this->createMkdirNode($directoryName);
        $result->addRootNode($lastMkdirNode);
      } else {
        $mkdirNode = $this->createMkdirNode($directoryName);
        $mkdirNode->addParent($lastMkdirNode);
        $lastMkdirNode->addChild($mkdirNode);
        $lastMkdirNode = $mkdirNode;
      }

      // set dependencies for all nodes in test
      foreach ($nodes as $node) {
        $node->addDependency($lastMkdirNode);
      }

      if ($params->isDebug()) {
        $dumpResultsNode = $this->createDumpResultsNode($directoryName);
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
   * Go through the whole tree (depth-first) and where needed add copy tasks which copies files between directories.
   * @param RootedTree $tree
   * @throws ExerciseConfigException
   */
  private function addCopyTasks(RootedTree $tree) {
    $stack = array_reverse($tree->getRootNodes());
    while (!empty($stack)) {
      $current = array_pop($stack); /** @var $current Node */

      $box = $current->getBox(); /** @var $box Box */
      if ($box) {
        foreach ($box->getInputPorts() as $port) {
          if (!$port->getVariableValue() || !$port->getVariableValue()->isFile() ||
              $port->getVariableValue()->getDirectory() === $box->getDirectory()) {
            continue;
          }

          // the directory of incoming files is different than directory of current box
          // therefore we have to copy the files to the current directory
          $copy = $this->createCopyNode($port->getVariableValue(), $box->getDirectory());
          $copy->addChild($current);
          foreach ($current->getParents() as $parent) {
            $copy->addParent($parent);
            $parent->removeChild($current);
            $parent->addChild($copy);
          }
          foreach ($current->getDependencies() as $dependency) {
            $copy->addDependency($dependency);
          }
          $current->clearParents();
          $current->addParent($copy);
          $current->addDependency($copy);

          // we also have to plug newly created output variable from copy box to the current input port...
          // this is needed because we need to have valid information in current box about directory of the variable
          $port->setVariableValue(current($copy->getBox()->getOutputPorts())->getVariableValue());
        }
      }

      // all children has to be crawled too
      foreach ($current->getChildren() as $child) {
        $stack[] = $child;
      }
    }
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
    // The algorithm follows...
    //   - The tree is searched with depth-first approach. Every node is processed in the following way.
    //   - If the node belongs to the test, the test identification is recorded and children of this node are processed.
    //   - If the node was optimised (has null test-id) then it is needed further processing.
    //   - We need to figure out the name of the directory which will be created for this optimized node and its
    //     sub-nodes. The name of the directory is composed of categories of boxes in the most left sub-branch which
    //     does not have test-id set.
    //   - Once the name is known, it is used as a directory for the processed node and all the sub-nodes in the most
    //     left branch of the tree.
    //   - After that, children of the node are processed.
    //   - The last step of the resolution is making sure that files are within the right directories. This is done
    //     through once again searching the tree and finding of if files needs to be copied from one directory to
    //     another. Therefore if the node from which the file came from was in different directory we need to copy
    //     the file into the directory of current node.

    // stack is holding pair of a node and a name of a directory
    // the directory which is applied only if the node is without test-id (hence optimised)
    $directoriesNodes = [];
    $stack = array_map(function (Node $node) { return [$node, null]; }, array_reverse($tree->getRootNodes()));
    while (!empty($stack)) {
      $currentPair = array_pop($stack);
      $current = $currentPair[0];
      $currentName = $currentPair[1];

      $testId = $current->getTestId();
      if ($testId !== null) {
        $testName = $context->getTestsNames()[$testId];
        $this->processNode($current, $testName, $directoriesNodes);
        $currentName = null;
      } else {
        // if the name is not set, we have to find it
        $currentName = $currentName ?? $this->findOptimizedNodesDirectory($current);
        $this->processNode($current, $currentName, $directoriesNodes);
      }

      // add children of current node into stack, the first children deserves better heritage from its father
      // (because its the favourite child!), therefore it inherits the privilege to use the name of the directory,
      // other children are not so lucky
      if (count($current->getChildren()) > 0) {
        $children = $current->getChildren();
        $first = array_shift($children);
        $stack[] = [$first, $currentName];
        foreach ($children as $child) {
          $stack[] = [$child, null];
        }
      }
    }

    $tree = $this->addDirectories($tree, $directoriesNodes, $params);
    $this->addCopyTasks($tree);
    return $tree;
  }

}
