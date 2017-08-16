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
   * @var BoxesOptimizer
   */
  private $boxesOptimizer;

  /**
   * @var BoxesCompiler
   */
  private $boxesCompiler;

  /**
   * @var VariablesResolver
   */
  private $variablesResolver;

  /**
   * @var TestDirectoriesResolver
   */
  private $testDirectoriesResolver;

  /**
   * ExerciseConfigValidator constructor.
   * @param PipelinesMerger $pipelinesMerger
   * @param BoxesSorter $boxesSorter
   * @param BoxesOptimizer $boxesOptimizer
   * @param BoxesCompiler $boxesCompiler
   * @param VariablesResolver $variablesResolver
   * @param TestDirectoriesResolver $testDirectoriesResolver
   */
  public function __construct(PipelinesMerger $pipelinesMerger,
      BoxesSorter $boxesSorter, BoxesOptimizer $boxesOptimizer,
      BoxesCompiler $boxesCompiler, VariablesResolver $variablesResolver,
      TestDirectoriesResolver $testDirectoriesResolver) {
    $this->pipelinesMerger = $pipelinesMerger;
    $this->boxesSorter = $boxesSorter;
    $this->boxesOptimizer = $boxesOptimizer;
    $this->boxesCompiler = $boxesCompiler;
    $this->variablesResolver = $variablesResolver;
    $this->testDirectoriesResolver = $testDirectoriesResolver;
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
    $this->variablesResolver->resolve($tests);
    $sortedTests = $this->boxesSorter->sort($tests);
    $optimized = $this->boxesOptimizer->optimize($sortedTests);
    $testDirectories = $this->testDirectoriesResolver->resolve($optimized);
    $jobConfig = $this->boxesCompiler->compile($testDirectories);
    return $jobConfig;
  }

}
