<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseCompilationSoftException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\Evaluation\IExercise;
use App\Helpers\ExerciseConfig\Compilation\BaseCompiler;
use App\Helpers\ExerciseConfig\Compilation\CompilationContext;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\JobConfig\JobConfig;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\RuntimeEnvironment;

/**
 * Compiler used for generating JobConfig structure from ExerciseConfig,
 * meaning, high-level format is compiled into low-level format which can be
 * executed on backend workers.
 */
class Compiler {

  public const EXERCISE_CONFIG_TYPES = ["simpleExerciseConfig", "advancedExerciseConfig"];

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
   * @throws ExerciseCompilationException
   */
  public function compile(IExercise $exercise,
      RuntimeEnvironment $runtimeEnvironment,
      CompilationParams $params): JobConfig {

    if (!static::checkConfigurationType($exercise->getConfigurationType())) {
      throw new ExerciseCompilationException("Unsupported configuration type");
    }

    // check submitted files if they are unique
    $uniqueFiles = array_unique($params->getFiles());
    if (count($params->getFiles()) !== count($uniqueFiles)) {
      throw new ExerciseCompilationSoftException(
        "Submitted files contains two or more files with the same name.",
        FrontendErrorMappings::E400_402__EXERCISE_COMPILATION_DUPLICATE_FILES
      );
    }

    if ($exercise->getExerciseConfig() === null) {
      throw new ExerciseCompilationException("The exercise has no configuration");
    }

    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    $environmentConfig = $exercise->getExerciseEnvironmentConfigByEnvironment($runtimeEnvironment);

    if ($environmentConfig === null) {
      throw new ExerciseCompilationException(sprintf(
        "The exercise has no configuration for environment '%s'",
        $runtimeEnvironment->getId()
      ));
    }

    $environmentConfigVariables = $this->loader->loadVariablesTable($environmentConfig->getParsedVariablesTable());

    $limits = array();

    /** @var HardwareGroup $hwGroup */
    foreach ($exercise->getHardwareGroups()->getValues() as $hwGroup) {
      $limitsConfig = $exercise->getLimitsByEnvironmentAndHwGroup($runtimeEnvironment, $hwGroup);
      if ($limitsConfig) {
        $parsedLimits = $limitsConfig->getParsedLimits();
      } else {
        $parsedLimits = [];
      }
      $limits[$hwGroup->getId()] = $this->loader->loadExerciseLimits($parsedLimits);
    }

    $context = CompilationContext::create($exerciseConfig, $environmentConfigVariables, $limits,
      $exercise->getHashedSupplementaryFiles(), $exercise->getExerciseTestsNames(), $runtimeEnvironment->getId());

    return $this->baseCompiler->compile($context, $params);
  }

  /**
   * Check if given configuration type is supported
   * @return bool
   */
  public static function checkConfigurationType(string $configurationType): bool {
    return in_array($configurationType, static::EXERCISE_CONFIG_TYPES);
  }
}
