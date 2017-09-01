<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\ExerciseConfig\Compilation\BaseCompiler;
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
  private $exerciseConfigCompiler;

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
    $this->exerciseConfigCompiler = $exerciseConfigCompiler;
    $this->loader = $loader;
  }

  /**
   * Generate job configuration from given exercise configuration.
   * @param Exercise|Assignment $exerciseAssignment
   * @param RuntimeEnvironment $runtimeEnvironment
   * @param string[] $submittedFiles
   * @return JobConfig
   */
  public function compile($exerciseAssignment,
      RuntimeEnvironment $runtimeEnvironment,
      array $submittedFiles): JobConfig {
    $exerciseConfig = $this->loader->loadExerciseConfig($exerciseAssignment->getExerciseConfig()->getParsedConfig());

    $environmentConfig = $exerciseAssignment->getExerciseEnvironmentConfigByEnvironment($runtimeEnvironment);
    $environmentConfigVariables = $this->loader->loadVariablesTable($environmentConfig->getParsedVariablesTable());

    $limits = array();
    foreach ($exerciseAssignment->getHardwareGroups()->getValues() as $hwGroup) {
      $limitsConfig = $exerciseAssignment->getLimitsByEnvironmentAndHwGroup($runtimeEnvironment, $hwGroup);
      $limits[$hwGroup->getId()] = $this->loader->loadExerciseLimits($limitsConfig->getParsedLimits());
    }

    return $this->exerciseConfigCompiler->compile($exerciseConfig,
      $environmentConfigVariables, $limits, $runtimeEnvironment->getId(),
      $submittedFiles);
  }
}
