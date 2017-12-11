<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\VariablesTable;


/**
 * Holder for various structure which are needed during compilation.
 */
class CompilationContext {

  /**
   * @var ExerciseConfig
   */
  private $exerciseConfig;

  /**
   * @var VariablesTable
   */
  private $environmentConfigVariables;

  /**
   * Returns array indexed by hwgroup.
   * @var array
   */
  private $limits;

  /**
   * @var array
   */
  private $exerciseFiles;

  /**
   * @var array
   */
  private $testsNames;

  /**
   * @var string
   */
  private $runtimeEnvironmentId;


  /**
   * @return ExerciseConfig
   */
  public function getExerciseConfig(): ExerciseConfig {
    return $this->exerciseConfig;
  }

  /**
   * @return VariablesTable
   */
  public function getEnvironmentConfigVariables(): VariablesTable {
    return $this->environmentConfigVariables;
  }

  /**
   * @return array
   */
  public function getLimits(): array {
    return $this->limits;
  }

  /**
   * File hashes indexed by file names.
   * @return array
   */
  public function getExerciseFiles(): array {
    return $this->exerciseFiles;
  }

  /**
   * Return tests names indexed by tests ids.
   * @return array
   */
  public function getTestsNames(): array {
    return $this->testsNames;
  }

  /**
   * @return string
   */
  public function getRuntimeEnvironmentId(): string {
    return $this->runtimeEnvironmentId;
  }


  /**
   * Factory.
   * @param ExerciseConfig $exerciseConfig
   * @param VariablesTable $environmentConfigVariables
   * @param array $limits
   * @param array $exerciseFiles
   * @param array $testsNames
   * @param string $runtimeEnvironmentId
   * @return CompilationContext
   */
  public static function create(ExerciseConfig $exerciseConfig, VariablesTable $environmentConfigVariables,
      array $limits, array $exerciseFiles, array $testsNames, string $runtimeEnvironmentId): CompilationContext {
    $context = new CompilationContext();
    $context->exerciseConfig = $exerciseConfig;
    $context->environmentConfigVariables = $environmentConfigVariables;
    $context->limits = $limits;
    $context->exerciseFiles = $exerciseFiles;
    $context->testsNames = $testsNames;
    $context->runtimeEnvironmentId = $runtimeEnvironmentId;
    return $context;
  }

}
