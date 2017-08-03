<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataOutBox;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise configuration compilation service. Which handles merging
 * pipelines in each test, in the process arrays of boxes is created indexed by
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
   * Merge data out boxes from previous pipeline with data in boxes from current
   * pipeline.
   * @note It is assumed that boxes have variables already set!
   * @param DataOutBox[] $dataOutBoxes
   * @param DataInBox[] $dataInBoxes
   * @return array
   */
  private function mergeDataBoxes(array $dataOutBoxes, array $dataInBoxes): array {
    ; // @todo
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
   * Merge all pipelines in test and return array of boxes.
   * @param Test $test
   * @param VariablesTable $environmentConfigVariables
   * @param string $runtimeEnvironmentId
   * @return Box[]
   */
  private function processTest(Test $test,
      VariablesTable $environmentConfigVariables,
      string $runtimeEnvironmentId): array {
    $boxes = array();

    // get pipelines either for specific environment or defaults for the test
    $testPipelines = $test->getEnvironment($runtimeEnvironmentId)->getPipelines();
    if (!$testPipelines || empty($testPipelines)) {
      $testPipelines = $test->getPipelines();
    }

    // go through all pipelines and merge their data boxes into resulting array
    // which has all variables tables set
    $lastDataOutBoxes = array();
    foreach ($testPipelines as $pipelineId => $pipeline) {

      // get database entity and then structured pipeline configuration
      $pipelineEntity = $this->pipelines->findOrThrow($pipelineId);
      $pipelineConfig = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig()->getParsedPipeline());

      // set all variables tables to boxes in pipeline
      $this->setVariablesTablesToPipelineBoxes($pipelineConfig,
        $pipeline->getVariablesTable(), $environmentConfigVariables,
        $pipelineConfig->getVariablesTable());

      // merge previous data out boxes with new input ones
      $boxes = array_merge($boxes, $this->mergeDataBoxes($lastDataOutBoxes, $pipelineConfig->getDataInBoxes()));
      // add non-data boxes to resulting array
      $boxes = array_merge($boxes, $pipelineConfig->getOtherBoxes());
      // data out boxes will be processed in next iteration
      $lastDataOutBoxes = $pipelineConfig->getDataOutBoxes();
    }
    $boxes = array_merge($boxes, $lastDataOutBoxes);

    return $boxes;
  }

  /**
   * For each test merge its pipelines and create array of boxes
   * @param ExerciseConfig $exerciseConfig
   * @param VariablesTable $environmentConfigVariables
   * @param string $runtimeEnvironmentId
   * @return array
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
