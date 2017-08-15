<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Pipeline\Box\JoinPipelinesBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Repository\Pipelines;


/**
 * Helper pair class.
 */
class NodePortPair {
  /** @var PortNode */
  public $node;
  /** @var Port */
  public $port;

  /**
   * NodePortPair constructor.
   * @param PortNode $node
   * @param Port $port
   */
  public function __construct(PortNode $node, Port $port) {
    $this->node = $node;
    $this->port = $port;
  }
}

/**
 * Helper pair class.
 */
class VariablePair {
  /** @var NodePortPair[]*/
  public $input = [];
  /** @var NodePortPair */
  public $output = null;
}


/**
 * Internal exercise configuration compilation service. Which handles merging
 * pipelines in each test, in the process tree of boxes is created indexed by
 * tests identifications.
 * @note No validations take place here! Configuration has to be correct and
 * validated before.
 */
class PipelinesMerger {

  /**
   * @var Pipelines
   */
  private $pipelines;

  /**
   * @var Loader
   */
  private $loader;

  /**
   * PipelinesMerger constructor.
   * @param Pipelines $pipelines
   * @param Loader $loader
   */
  public function __construct(Pipelines $pipelines, Loader $loader) {
    $this->pipelines = $pipelines;
    $this->loader = $loader;
  }


  /**
   * To all boxes in pipeline set appropriate variables.
   * @param MergeTree $pipelineTree
   * @param VariablesTable $exerciseConfigVariables
   * @param VariablesTable $environmentConfigVariables
   * @param VariablesTable $pipelineVariables
   */
  private function setVariablesTablesToPipelineBoxes(MergeTree $pipelineTree,
      VariablesTable $exerciseConfigVariables,
      VariablesTable $environmentConfigVariables,
      VariablesTable $pipelineVariables) {
    foreach ($pipelineTree->getAllNodes() as $node) {
      $node->setExerciseConfigVariables($exerciseConfigVariables)
        ->setEnvironmentConfigVariables($environmentConfigVariables)
        ->setPipelineVariables($pipelineVariables);
    }
  }

  /**
   * Merge two trees consisting of boxes. Input and output boxes which matches
   * will be deleted and connection between corresponding previous/next boxes
   * will be realised by adding intermediate node with no assigned box.
   * @param MergeTree $first
   * @param MergeTree $second
   * @return MergeTree new instance of tree
   */
  private function mergeTrees(MergeTree $first, MergeTree $second): MergeTree {

    // index output nodes from first tree with their variable names
    $outVars = array();
    foreach ($first->getOutputNodes() as $outNode) {
      $inPort = current($outNode->getBox()->getInputPorts());
      $outVars[$inPort->getVariable()] = new NodePortPair($outNode, $inPort);
    }

    // search input nodes for the ones which match variable names with the output ones
    $newSecondInput = array();
    $joinNodes = array();
    foreach ($second->getInputNodes() as $inNode) {
      $outPort = current($inNode->getBox()->getOutputPorts());
      if (array_key_exists($outPort->getVariable(), $outVars)) {
        // match found... merge input and output by connecting previous and next node
        $outNode = $outVars[$outPort->getVariable()]->node;
        $inPort = $outVars[$outPort->getVariable()]->port;
        $previous = current($outNode->getParents());
        $next = current($inNode->getChildren());

        // remove input and output data nodes from tree
        $previous->removeChild($outNode);
        $next->removeParent($inNode);

        // create new custom join box
        $joinBox = new JoinPipelinesBox();
        $joinBox->setInputPort($inPort);
        $joinBox->setOutputPort($outPort);

        // add new connections
        $joinNodes[] = $joinNode = new PortNode($joinBox);
        $previous->addChild($inPort->getName(), $joinNode);
        $joinNode->addParent($inPort->getName(), $previous);
        $next->addParent($outPort->getName(), $joinNode);
        $joinNode->addChild($outPort->getName(), $next);

        // delete variable from the indexed array to be able to say which nodes have to stay at the end
        unset($outVars[$outPort->getVariable()]);
      } else {
        // ports not matched
        $newSecondInput[] = $inNode;
      }
    }

    // transform remaining output from first tree into classic array
    $newFirstOutput = array_values($outVars);

    // set all necessary things into returned tree
    $tree = new MergeTree();
    $tree->setInputNodes(array_merge($first->getInputNodes(), $newSecondInput));
    $tree->setOutputNodes(array_merge($newFirstOutput, $second->getOutputNodes()));
    $tree->setOtherNodes(array_merge($first->getOtherNodes(), $second->getOtherNodes(), $joinNodes));
    return $tree;
  }

  /**
   * Build pipeline tree with all appropriate connections.
   * @param string $pipelineId
   * @param string $testId
   * @param Pipeline $pipeline
   * @return MergeTree
   */
  private function buildPipelineTree(string $pipelineId, string $testId, Pipeline $pipeline): MergeTree {

    // construct all nodes from pipeline, we need to have list of boxes
    // indexed by name for searching and second list as execution queue
    // and also set of variables with references to input and output box
    $queue = array();
    $nodes = array();
    $variables = array(); // array of pairs of pairs
    foreach ($pipeline->getAll() as $box) {
      $node = new PortNode($box, $pipelineId, $testId);
      $queue[] = $node;
      $nodes[$box->getName()] = $node;

      // go through all ports of box and assign them to appropriate variables set
      foreach ($box->getInputPorts() as $inPort) {
        $varName = $inPort->getVariable();
        if (empty($varName)) {
          continue; // variable in port is not specified... jump over
        }
        if (!array_key_exists($varName, $variables)) {
          $variables[$varName] = new VariablePair();
        }
        $variables[$varName]->input[] = new NodePortPair($node, $inPort);
      }
      foreach ($box->getOutputPorts() as $outPort) {
        $varName = $outPort->getVariable();
        if (empty($varName)) {
          continue; // variable in port is not specified... jump over
        }
        if (!array_key_exists($varName, $variables)) {
          $variables[$varName] = array();
        }
        $variables[$varName]->output = new NodePortPair($node, $outPort);
      }
    }

    // process queue and make connections between nodes
    $tree = new MergeTree();
    foreach($queue as $node) {
      $box = $node->getBox();
      foreach ($box->getInputPorts() as $inPort) {
        $varName = $inPort->getVariable();
        if (empty($varName)) {
          continue; // variable in port is not specified... jump over
        }
        $parent = $variables[$varName]->output->node;
        if ($parent->isInTree()) {
          continue;
        }
        $node->addParent($inPort->getName(), $parent);
        $parent->addChild($variables[$varName]->output->port->getName(), $node);
      }
      foreach ($box->getOutputPorts() as $outPort) {
        $varName = $outPort->getVariable();
        if (empty($varName)) {
          continue; // variable in port is not specified... jump over
        }

        // go through all children for this port
        foreach ($variables[$varName]->input as $childPair) {
          $child = $childPair->node;
          $port = $childPair->port;
          if ($child->isInTree()) {
            continue;
          }
          $node->addChild($outPort->getName(), $child);
          $child->addParent($port->getName(), $node);
        }
      }

      // ... visited flag
      $node->setInTree(true);
    }

    // add input boxes into tree
    foreach ($pipeline->getDataInBoxes() as $box) {
      $tree->addInputNode($nodes[$box->getName()]);
    }

    // add output boxes into tree
    foreach ($pipeline->getDataOutBoxes() as $box) {
      $tree->addOutputNode($nodes[$box->getName()]);
    }

    // add other boxes into tree
    foreach ($pipeline->getOtherBoxes() as $box) {
      $tree->addOtherNode($nodes[$box->getName()]);
    }

    // ... and return resulting tree
    return $tree;
  }

  /**
   * Process pipeline, which means creating its tree and merging two trees
   * @param string $pipelineId
   * @param string $testId
   * @param MergeTree $tree
   * @param VariablesTable $environmentConfigVariables
   * @param PipelineVars $pipelineVars
   * @param Pipeline $pipelineConfig
   * @return MergeTree new instance of merge tree
   */
  private function processPipeline(string $pipelineId, string $testId,
      MergeTree $tree, VariablesTable $environmentConfigVariables,
      PipelineVars $pipelineVars, Pipeline $pipelineConfig): MergeTree {

    // build tree for given pipeline
    $pipelineTree = $this->buildPipelineTree($pipelineId, $testId, $pipelineConfig);
    // set all variables tables to boxes in pipeline
    $this->setVariablesTablesToPipelineBoxes($pipelineTree,
      $pipelineVars->getVariablesTable(), $environmentConfigVariables,
      $pipelineConfig->getVariablesTable());

    // merge given tree and currently created pipeline tree
    $mergedTree = $this->mergeTrees($tree, $pipelineTree);
    return $mergedTree;
  }

  /**
   * Merge all pipelines in test and return array of boxes.
   * @param string $testId
   * @param Test $test
   * @param VariablesTable $environmentConfigVariables
   * @param string $runtimeEnvironmentId
   * @return MergeTree
   */
  private function processTest(string $testId, Test $test,
      VariablesTable $environmentConfigVariables,
      string $runtimeEnvironmentId): MergeTree {

    // get pipelines either for specific environment or defaults for the test
    $testPipelines = $test->getEnvironment($runtimeEnvironmentId)->getPipelines();
    if (!$testPipelines || empty($testPipelines)) {
      $testPipelines = $test->getPipelines();
    }

    // go through all pipelines and merge their data boxes into resulting array
    // which has all variables tables set
    $tree = new MergeTree();
    foreach ($testPipelines as $pipelineId => $pipelineVars) {

      // get database entity and then structured pipeline configuration
      $pipelineEntity = $this->pipelines->findOrThrow($pipelineId);
      $pipelineConfig = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig()->getParsedPipeline());

      // process pipeline and merge it to already existing tree
      $tree = $this->processPipeline($pipelineId, $testId, $tree, $environmentConfigVariables, $pipelineVars, $pipelineConfig);
    }

    return $tree;
  }

  /**
   * For each test merge its pipelines and create array of boxes
   * @param ExerciseConfig $exerciseConfig
   * @param VariablesTable $environmentConfigVariables
   * @param string $runtimeEnvironmentId
   * @return MergeTree[]
   */
  public function merge(ExerciseConfig $exerciseConfig,
      VariablesTable $environmentConfigVariables,
      string $runtimeEnvironmentId): array {
    $tests = array();
    foreach ($exerciseConfig->getTests() as $testId => $test) {
      $tests[$testId] = $this->processTest($testId, $test, $environmentConfigVariables, $runtimeEnvironmentId);
    }

    return $tests;
  }

}
