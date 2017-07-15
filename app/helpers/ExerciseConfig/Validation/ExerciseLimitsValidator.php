<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\ExerciseLimits;
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
   * ExerciseConfigValidator constructor.
   * @param Pipelines $pipelines
   */
  public function __construct(Pipelines $pipelines) {
    $this->pipelines = $pipelines;
  }


  /**
   * Validate exercise limits.
   * @param ExerciseLimits $exerciseLimits
   * @param ExerciseConfig $exerciseConfig
   * @param string $environmentId
   * @throws ExerciseConfigException
   */
  public function validate(ExerciseLimits $exerciseLimits, ExerciseConfig $exerciseConfig, string $environmentId) {

    // @todo: only identification of box is not enough in limits

    $pipelines = array();

    foreach ($exerciseLimits->getLimitsArray() as $boxId => $limits) {
      ;
    }
  }

}
