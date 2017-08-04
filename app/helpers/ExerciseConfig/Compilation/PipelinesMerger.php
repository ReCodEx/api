<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Tree;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise configuration compilation service. Which handles merging
 * pipelines in each test, in the process tree of boxes is created indexed by
 * tests identifications.
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
   * @param Pipeline $pipelineConfig
   * @param VariablesTable $exerciseConfigVariables
   * @param VariablesTable $environmentConfigVariables
   * @param VariablesTable $pipelineVariables
   */
  private function setVariablesTablesToPipelineBoxes(Pipeline $pipelineConfig,
      VariablesTable $exerciseConfigVariables,
      VariablesTable $environmentConfigVariables,
      VariablesTable $pipelineVariables) {
    foreach ($pipelineConfig->getAll() as $box) {
      $box->setExerciseConfigVariables($exerciseConfigVariables)
        ->setEnvironmentConfigVariables($environmentConfigVariables)
        ->setPipelineVariables($pipelineVariables);
    }
  }

  /**
   * Merge two trees consisting of boxes. Input and output boxes which matches
   * will be deleted and connections will be established between corresponding
   * previous/next boxes.
   * @param Tree $tree
   * @param Tree $pipelineTree
   * @return Tree
   */
  private function mergeTrees(Tree $tree, Tree $pipelineTree): Tree {
    // @todo
  }

  /**
   * Build pipeline tree with all appropriate connections.
   * @param Pipeline $pipeline
   * @return Tree
   */
  private function buildPipelineTree(Pipeline $pipeline): Tree {
    // @todo: use visited flag
  }

  /**
   * Process pipeline, which means creating its tree and merging two trees
   * @note New tree is returned!
   * @param Tree $tree
   * @param VariablesTable $environmentConfigVariables
   * @param PipelineVars $pipelineVars
   * @param Pipeline $pipelineConfig
   * @return Tree
   */
  private function processPipeline(Tree $tree,
      VariablesTable $environmentConfigVariables, PipelineVars $pipelineVars,
      Pipeline $pipelineConfig): Tree {

    // set all variables tables to boxes in pipeline
    $this->setVariablesTablesToPipelineBoxes($pipelineConfig,
      $pipelineVars->getVariablesTable(), $environmentConfigVariables,
      $pipelineConfig->getVariablesTable());

    // build tree for given pipeline
    $pipelineTree = $this->buildPipelineTree($pipelineConfig);
    // merge given tree and currently created pipeline tree
    $mergedTree = $this->mergeTrees($tree, $pipelineTree);
    return $mergedTree;
  }

  /**
   * Merge all pipelines in test and return array of boxes.
   * @param Test $test
   * @param VariablesTable $environmentConfigVariables
   * @param string $runtimeEnvironmentId
   * @return Tree
   */
  private function processTest(Test $test,
      VariablesTable $environmentConfigVariables,
      string $runtimeEnvironmentId): Tree {

    // get pipelines either for specific environment or defaults for the test
    $testPipelines = $test->getEnvironment($runtimeEnvironmentId)->getPipelines();
    if (!$testPipelines || empty($testPipelines)) {
      $testPipelines = $test->getPipelines();
    }

    // go through all pipelines and merge their data boxes into resulting array
    // which has all variables tables set
    $tree = new Tree();
    foreach ($testPipelines as $pipelineId => $pipelineVars) {

      // get database entity and then structured pipeline configuration
      $pipelineEntity = $this->pipelines->findOrThrow($pipelineId);
      $pipelineConfig = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig()->getParsedPipeline());

      // process pipeline and merge it to already existing tree
      $tree = $this->processPipeline($tree, $environmentConfigVariables, $pipelineVars, $pipelineConfig);
    }

    return $tree;
  }

  /**
   * For each test merge its pipelines and create array of boxes
   * @param ExerciseConfig $exerciseConfig
   * @param VariablesTable $environmentConfigVariables
   * @param string $runtimeEnvironmentId
   * @return Tree[]
   */
  public function merge(ExerciseConfig $exerciseConfig,
      VariablesTable $environmentConfigVariables,
      string $runtimeEnvironmentId): array {
    $tests = array();
    foreach ($exerciseConfig->getTests() as $testId => $test) {
      $tests[$testId] = $this->processTest($test, $environmentConfigVariables, $runtimeEnvironmentId);
    }

    return $tests;
  }

}
