<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\Loader;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTest;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise limits validation service.
 */
class ExerciseLimitsValidator {

  /**
   * @var Pipelines
   */
  private $pipelines;

  /**
   * @var Loader
   */
  private $loader;

  /**
   * ExerciseConfigValidator constructor.
   * @param Pipelines $pipelines
   * @param Loader $loader
   */
  public function __construct(Pipelines $pipelines, Loader $loader) {
    $this->pipelines = $pipelines;
    $this->loader = $loader;
  }


  /**
   * Validate exercise limits.
   * For more detailed description look at @ref App\Helpers\ExerciseConfig\Validator
   * @param Exercise $exercise
   * @param ExerciseLimits $exerciseLimits
   * @throws ExerciseConfigException
   */
  public function validate(Exercise $exercise, ExerciseLimits $exerciseLimits) {
    $exerciseTests = $exercise->getExerciseTestsIds();

    foreach ($exerciseLimits->getLimitsArray() as $testId => $testLimits) {
      if (!in_array($testId, $exerciseTests)) {
        throw new ExerciseConfigException(sprintf(
          "Test with id '%s' is not present in the exercise configuration",
          $testId
        ));
      }

      if ($testLimits->getMemoryLimit() === 0) {
        throw new ExerciseConfigException(sprintf("Test with id '%s' needs to have a memory limit", $testId));
      }

      if ($testLimits->getCpuTime() === 0.0 && $testLimits->getWallTime() === 0.0) {
        throw new ExerciseConfigException(sprintf("Test with id '%s' needs to have a time limit (either cpu or wall)", $testId));
      }
    }
  }

}
