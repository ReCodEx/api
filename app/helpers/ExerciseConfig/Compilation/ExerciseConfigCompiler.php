<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
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
   * @var VariablesResolver
   */
  private $variablesResolver;

  /**
   * ExerciseConfigValidator constructor.
   * @param PipelinesMerger $pipelinesMerger
   * @param BoxesSorter $boxesSorter
   * @param TestBoxesOptimizer $testBoxesOptimizer
   * @param BoxesCompiler $boxesCompiler
   */
  public function __construct(PipelinesMerger $pipelinesMerger,
      BoxesSorter $boxesSorter, TestBoxesOptimizer $testBoxesOptimizer,
      BoxesCompiler $boxesCompiler, VariablesResolver $variablesResolver) {
    $this->pipelinesMerger = $pipelinesMerger;
    $this->boxesSorter = $boxesSorter;
    $this->testBoxesOptimizer = $testBoxesOptimizer;
    $this->boxesCompiler = $boxesCompiler;
    $this->variablesResolver = $variablesResolver;
  }


  /**
   * Compile ExerciseConfig to JobConfig
   * @param ExerciseConfig $exerciseConfig
   * @param VariablesTable $environmentConfigVariables
   * @param string $runtimeEnvironmentId
   * @return JobConfig
   * @throws ExerciseConfigException
   */
  public function compile(ExerciseConfig $exerciseConfig,
      VariablesTable $environmentConfigVariables, string $runtimeEnvironmentId): JobConfig {
    $tests = $this->pipelinesMerger->merge($exerciseConfig, $environmentConfigVariables, $runtimeEnvironmentId);
    $tests = $this->variablesResolver->resolve($tests);
    $sortedTests = $this->boxesSorter->sort($tests);
    $executionPipeline = $this->testBoxesOptimizer->optimize($sortedTests);
    $jobConfig = $this->boxesCompiler->compile($executionPipeline);
    return $jobConfig;
  }

}
