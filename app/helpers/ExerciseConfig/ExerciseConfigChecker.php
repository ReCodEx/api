<?php
namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Model\Entity\Exercise;
use App\Model\Entity\RuntimeEnvironment;
use Nette\SmartObject;

/**
 * A helper that hopes to detect broken exercise configurations by attempting to compile them.
 */
class ExerciseConfigChecker {
  use SmartObject;

  private $compiler;

  private $validator;

  private $loader;

  public function  __construct(ExerciseConfig\Compiler $compiler, ExerciseConfig\Validator $validator, ExerciseConfig\Loader $loader) {
    $this->compiler = $compiler;
    $this->validator = $validator;
    $this->loader = $loader;
  }

  /**
   * Make up names of submitted files for a runtime environment.
   * This is necessary because we do not have any real submissions yet, but the compiler needs their names.
   * TODO when we implement a mechanism that ensures further constraints on submitted files, it must be reflected here
   */
  private function conjureSubmittedFiles(RuntimeEnvironment $environment) {
    $extension = current($environment->getExtensionsList());
    return ["main.{$extension}"];
  }

  /**
   * Check the configuration of an exercise (including all environment configs) and set the `isBroken` flag if there is
   * an error.
   * @param Exercise $exercise the exercise whose configuration should be checked
   */
  public function check(Exercise $exercise) {
    try {
      $config = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
      $this->validator->validateExerciseConfig($exercise, $config);
    } catch (ExerciseConfigException $exception) {
      $exercise->setBroken(sprintf("Global exercise configuration is invalid: %s", $exception->getMessage()));
      return;
    }

    /** @var RuntimeEnvironment $environment */
    $environment = null;

    try {
      foreach ($exercise->getRuntimeEnvironments() as $environment) {
        $envConfig = $exercise->getExerciseEnvironmentConfigByEnvironment($environment);
        $table = $this->loader->loadVariablesTable($envConfig->getParsedVariablesTable());
        $this->validator->validateEnvironmentConfig($exercise, $table);
        $this->compiler->compile(
          $exercise,
          $environment,
          CompilationParams::create($this->conjureSubmittedFiles($environment))
        );
      }
      $exercise->setNotBroken();
    } catch (ExerciseConfigException $exception) {
      $exercise->setBroken(sprintf(
        "Error in exercise configuration for environment '%s': %s",
        $environment !== null ? $environment->getId() : "UNKNOWN",
        $exception->getMessage()
      ));
    }
  }
}

