<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\ExerciseConfig\Compilation\BaseCompiler;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\JobConfig\JobConfig;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\RuntimeEnvironment;

/**
 * Compiler used for generating JobConfig structure from ExerciseConfig,
 * meaning, high-level format is compiled into low-level format which can be
 * executed on backend workers.
 */
class Compiler {

  /**
   * @var BaseCompiler
   */
  private $baseCompiler;

  /**
   * @var Loader
   */
  private $loader;

  /**
   * Compiler constructor.
   * @param BaseCompiler $exerciseConfigCompiler
   * @param Loader $loader
   */
  public function __construct(BaseCompiler $exerciseConfigCompiler,
      Loader $loader) {
    $this->baseCompiler = $exerciseConfigCompiler;
    $this->loader = $loader;
  }

  /**
   * Generate job configuration from given exercise configuration.
   * @param Exercise|Assignment $exerciseAssignment
   * @param RuntimeEnvironment $runtimeEnvironment
   * @param CompilationParams $params
   * @return JobConfig
   */
  public function compile($exerciseAssignment,
      RuntimeEnvironment $runtimeEnvironment,
      CompilationParams $params): JobConfig {
    $exerciseConfig = $this->loader->loadExerciseConfig($exerciseAssignment->getExerciseConfig()->getParsedConfig());

    $environmentConfig = $exerciseAssignment->getExerciseEnvironmentConfigByEnvironment($runtimeEnvironment);
    $environmentConfigVariables = $this->loader->loadVariablesTable($environmentConfig->getParsedVariablesTable());

    $limits = array();
    foreach ($exerciseAssignment->getHardwareGroups()->getValues() as $hwGroup) {
      $limitsConfig = $exerciseAssignment->getLimitsByEnvironmentAndHwGroup($runtimeEnvironment, $hwGroup);
      if ($limitsConfig) {
        $parsedLimits = $limitsConfig->getParsedLimits();
      } else {
        $parsedLimits = [];
      }
      $limits[$hwGroup->getId()] = $this->loader->loadExerciseLimits($parsedLimits);
    }

    return $this->baseCompiler->compile($exerciseConfig,
      $environmentConfigVariables, $limits, $runtimeEnvironment->getId(),
      $params);
  }
}
