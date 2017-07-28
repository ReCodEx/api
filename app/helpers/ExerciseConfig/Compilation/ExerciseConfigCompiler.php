<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\JobConfig\JobConfig;


/**
 * Internal exercise configuration compilation service.
 */
class ExerciseConfigCompiler {

  /**
   * @var PipelinesMerger
   */
  private $pipelinesMerger;

  /**
   * @var BoxesSorter
   */
  private $boxesSorter;

  /**
   * @var TestBoxesOptimizer
   */
  private $testBoxesOptimizer;

  /**
   * @var BoxesCompiler
   */
  private $boxesCompiler;

  /**
   * ExerciseConfigValidator constructor.
   * @param PipelinesMerger $pipelinesMerger
   * @param BoxesSorter $boxesSorter
   * @param TestBoxesOptimizer $testBoxesOptimizer
   * @param BoxesCompiler $boxesCompiler
   */
  public function __construct(PipelinesMerger $pipelinesMerger,
      BoxesSorter $boxesSorter, TestBoxesOptimizer $testBoxesOptimizer,
      BoxesCompiler $boxesCompiler) {
    $this->pipelinesMerger = $pipelinesMerger;
    $this->boxesSorter = $boxesSorter;
    $this->testBoxesOptimizer = $testBoxesOptimizer;
    $this->boxesCompiler = $boxesCompiler;
  }


  /**
   * Compile ExerciseConfig to JobConfig
   * @param ExerciseConfig $exerciseConfig
   * @param VariablesTable $variablesTable
   * @return JobConfig
   */
  public function compile(ExerciseConfig $exerciseConfig, VariablesTable $variablesTable): JobConfig {
    $tests = $this->pipelinesMerger->merge($exerciseConfig);
    $tests = $this->boxesSorter->sort($tests);
    $executionPipeline = $this->testBoxesOptimizer->optimize($tests);
    $jobConfig = $this->boxesCompiler->compile($executionPipeline, $variablesTable);
    return $jobConfig;
  }

}
