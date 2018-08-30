<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Pipeline\Box\JoinPipelinesBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\PipelinesCache;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;

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
  /** @var NodePortPair[] */
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
   * @var PipelinesCache
   */
  private $pipelinesCache;

  /**
   * @var VariablesResolver
   */
  private $variablesResolver;

  /**
   * PipelinesMerger constructor.
   * @param PipelinesCache $pipelinesCache
   * @param VariablesResolver $variablesResolver
   */
  public function __construct(PipelinesCache $pipelinesCache,
                              VariablesResolver $variablesResolver) {
    $this->pipelinesCache = $pipelinesCache;
    $this->variablesResolver = $variablesResolver;
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
    $outVars = array(); /** @var NodePortPair[] $outVars */
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
        $previousChildPort = $previous->findChildPort($outNode);
        $nextParentPort = $next->findParentPort($inNode);
        $previous->removeChild($outNode);
        $next->removeParent($inNode);

        // create new custom join box
        $joinBox = new JoinPipelinesBox($outNode->getBox()->getName() . "__" . $inNode->getBox()->getName() . "__join-box");
        // note: setting input and output ports from parent/child should solve resolving variables value
        $joinBox->setInputPort($inPort);
        $joinBox->setOutputPort($outPort);
        // for join box create new node in tree
        $joinNodes[] = $joinNode = new PortNode($joinBox, $next->getPipelineId(), $next->getTestId());

        // engage join node into tree
        $previous->addChild($previousChildPort, $joinNode);
        $joinNode->addParent($inPort->getName(), $previous);
        $next->addParent($nextParentPort, $joinNode);
        $joinNode->addChild($outPort->getName(), $next);

        // delete variable from the indexed array to be able to say which nodes have to stay at the end
        unset($outVars[$outPort->getVariable()]);
      } else {
        // ports not matched
        $newSecondInput[] = $inNode;
      }
    }

    // transform remaining output from first tree into classic array
    $newFirstOutput = [];
    foreach ($outVars as $outVar) {
      $newFirstOutput[] = $outVar->node;
    }

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
    $queue = array(); /** @var PortNode[] $queue */
    $nodes = array(); /** @var PortNode[] $nodes */
    $variables = array(); /** @var VariablePair[] $variables */
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
          $variables[$varName] = new VariablePair();
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
        $parent = $variables[$varName]->output ? $variables[$varName]->output->node : null;
        if ($parent === null || $parent->isInTree()) {
          continue; // parent output port does not have to be present for input ports
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
   * Process pipeline, which means creating its tree.
   * @param string $testId
   * @param PipelineVars $pipelineVars
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @return MergeTree new instance of merge tree
   * @throws ExerciseConfigException
   */
  private function processPipeline(string $testId, PipelineVars $pipelineVars,
      CompilationContext $context, CompilationParams $params): MergeTree {
    $pipelineId = $pipelineVars->getId();

    // get database entity and then structured pipeline configuration
    try {
      $pipelineEntity = $this->pipelinesCache->getPipeline($pipelineId);
      $pipelineConfig = $this->pipelinesCache->getNewPipelineConfig($pipelineId);
      $pipelineFiles = $pipelineEntity->getHashedSupplementaryFiles();
    } catch (NotFoundException $e) {
      throw new ExerciseConfigException("Pipeline '$pipelineId' not found in environment");
    }

    // build tree for given pipeline
    $pipelineTree = $this->buildPipelineTree($pipelineId, $testId, $pipelineConfig);
    // resolve all variables in pipeline tree
    $this->variablesResolver->resolve($pipelineTree,
      $pipelineVars->getVariablesTable(),
      $pipelineConfig->getVariablesTable(),
      $pipelineFiles, $context, $params);

    return $pipelineTree;
  }

  /**
   * Merge all pipelines in test and return array of boxes.
   * @param string $testId
   * @param Test $test
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @return MergeTree
   * @throws ExerciseConfigException
   */
  private function processTest(string $testId, Test $test, CompilationContext $context,
      CompilationParams $params): MergeTree {

    // get pipelines either for specific environment
    $runtimeEnvironmentId = $context->getRuntimeEnvironmentId();
    $environment = $test->getEnvironment($runtimeEnvironmentId);
    $testPipelines = $environment->getPipelines();

    // check if there are any pipelines in specified environment
    if (count($testPipelines) === 0) {
      throw new ExerciseConfigException("Exercise configuration does not specify any pipelines for environment '$runtimeEnvironmentId' and test '$testId'");
    }

    // go through all pipelines and merge their data boxes into resulting array
    // which has all variables tables set
    $tree = new MergeTree();
    foreach ($testPipelines as $pipelineVars) {

      // process pipeline and merge it to already existing tree
      $pipelineTree = $this->processPipeline($testId, $pipelineVars, $context, $params);
      // merge given tree and currently created pipeline tree
      $tree = $this->mergeTrees($tree, $pipelineTree);
    }

    return $tree;
  }

  /**
   * For each test merge its pipelines and create array of boxes
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @return MergeTree[]
   * @throws ExerciseConfigException
   */
  public function merge(CompilationContext $context, CompilationParams $params): array {
    if (count($context->getExerciseConfig()->getTests()) === 0) {
      throw new ExerciseConfigException("Exercise configuration does not specify any tests");
    }

    $tests = array();
    foreach ($context->getExerciseConfig()->getTests() as $testId => $test) {
      // find test identification in tests names array and retrieve test name
      if (!array_key_exists($testId, $context->getTestsNames())) {
        throw new ExerciseConfigException("Test with id '{$testId}' does not exist in exercise.");
      }
      $testName = $context->getTestsNames()[$testId];

      // process test
      $tests[$testName] = $this->processTest($testId, $test, $context, $params);
    }

    return $tests;
  }

}
