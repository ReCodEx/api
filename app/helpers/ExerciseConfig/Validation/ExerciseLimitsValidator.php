<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\Loader;
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
   * @param ExerciseLimits $exerciseLimits
   * @param ExerciseConfig $exerciseConfig
   * @throws ExerciseConfigException
   */
  public function validate(ExerciseLimits $exerciseLimits, ExerciseConfig $exerciseConfig) {
    foreach ($exerciseLimits->getLimitsArray() as $testId => $firstLevel) {
      $test = $exerciseConfig->getTest($testId);

      if ($test === NULL) {
        throw new ExerciseConfigException(sprintf(
          "Test %s is not present in the exercise configuration",
          $testId
        ));
      }
    }
  }

}
