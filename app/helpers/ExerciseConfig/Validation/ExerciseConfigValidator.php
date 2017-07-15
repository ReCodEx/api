<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise configuration validation service.
 */
class ExerciseConfigValidator {

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
   * Validate exercise configuration.
   * @param ExerciseConfig $config
   * @param array $variablesTables indexed with runtime environment
   * identification and containing variables table
   * @throws ExerciseConfigException
   */
  public function validate(ExerciseConfig $config, array $variablesTables) {
    // @todo
  }

}
