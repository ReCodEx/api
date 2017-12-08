<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\JobConfig\JobConfig;


/**
 * Internal exercise configuration compilation service.
 */
class BaseCompiler {

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
   * @var TestDirectoriesResolver
   */
  private $testDirectoriesResolver;

  /**
   * ExerciseConfigValidator constructor.
   * @param PipelinesMerger $pipelinesMerger
   * @param BoxesSorter $boxesSorter
   * @param BoxesOptimizer $boxesOptimizer
   * @param BoxesCompiler $boxesCompiler
   * @param TestDirectoriesResolver $testDirectoriesResolver
   */
  public function __construct(PipelinesMerger $pipelinesMerger,
      BoxesSorter $boxesSorter, BoxesOptimizer $boxesOptimizer,
      BoxesCompiler $boxesCompiler, TestDirectoriesResolver $testDirectoriesResolver) {
    $this->pipelinesMerger = $pipelinesMerger;
    $this->boxesSorter = $boxesSorter;
    $this->boxesOptimizer = $boxesOptimizer;
    $this->boxesCompiler = $boxesCompiler;
    $this->testDirectoriesResolver = $testDirectoriesResolver;
  }


  /**
   * Compile ExerciseConfig to JobConfig
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @return JobConfig
   * @throws ExerciseConfigException
   */
  public function compile(CompilationContext $context, CompilationParams $params): JobConfig {
    $tests = $this->pipelinesMerger->merge($context, $params);
    $sortedTests = $this->boxesSorter->sort($tests);
    $optimized = $this->boxesOptimizer->optimize($sortedTests);
    $testDirectories = $this->testDirectoriesResolver->resolve($optimized, $params);
    $jobConfig = $this->boxesCompiler->compile($testDirectories, $context, $params);
    return $jobConfig;
  }

}
