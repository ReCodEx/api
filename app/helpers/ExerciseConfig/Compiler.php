<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\Evaluation\IExercise;
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
   * @param IExercise $exercise
   * @param RuntimeEnvironment $runtimeEnvironment
   * @param CompilationParams $params
   * @return JobConfig
   * @throws ExerciseConfigException
   */
  public function compile(IExercise $exercise,
      RuntimeEnvironment $runtimeEnvironment,
      CompilationParams $params): JobConfig {

    // check submitted files if they are unique
    $uniqueFiles = array_unique($params->getFiles());
    if (count($params->getFiles()) !== count($uniqueFiles)) {
      throw new ExerciseConfigException("Submitted files contains two or more files with the same name.");
    }

    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    $environmentConfig = $exercise->getExerciseEnvironmentConfigByEnvironment($runtimeEnvironment);
    $environmentConfigVariables = $this->loader->loadVariablesTable($environmentConfig->getParsedVariablesTable());

    $limits = array();
    foreach ($exercise->getHardwareGroups()->getValues() as $hwGroup) {
      $limitsConfig = $exercise->getLimitsByEnvironmentAndHwGroup($runtimeEnvironment, $hwGroup);
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
