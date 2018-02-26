<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\EntityMetadata\HwGroupMeta;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\Loader;
use App\Model\Entity\Exercise;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise limits validation service.
 */
class ExerciseLimitsValidator {

  /**
   * @var Loader
   */
  private $loader;

  /**
   * ExerciseConfigValidator constructor.
   * @param Loader $loader
   */
  public function __construct(Loader $loader) {
    $this->loader = $loader;
  }


  /**
   * Validate exercise limits.
   * For more detailed description look at @ref App\Helpers\ExerciseConfig\Validator
   * @param Exercise $exercise
   * @param HwGroupMeta $hwGroupMeta
   * @param ExerciseLimits $exerciseLimits
   * @throws ExerciseConfigException
   */
  public function validate(Exercise $exercise, HwGroupMeta $hwGroupMeta, ExerciseLimits $exerciseLimits) {
    $exerciseTests = $exercise->getExerciseTestsIds();

    $cpuTimeSum = 0;
    $wallTimeSum = 0;
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

      $cpuTimeSum += $testLimits->getCpuTime();
      $wallTimeSum += $testLimits->getWallTime();

      if ($testLimits->getMemoryLimit() > $hwGroupMeta->getMemory()) {
        throw new ExerciseConfigException(sprintf("Test with id '%s' has exceeded memory limit '%d'", $testId, $hwGroupMeta->getMemory()));
      }

      if ($testLimits->getCpuTime() > $hwGroupMeta->getCpuTimePerTest()) {
        throw new ExerciseConfigException(sprintf("Test with id '%s' has exceeded cpu time limit '%d'", $testId, $hwGroupMeta->getCpuTimePerTest()));
      }

      if ($testLimits->getWallTime() > $hwGroupMeta->getWallTimePerTest()) {
        throw new ExerciseConfigException(sprintf("Test with id '%s' has exceeded wall time limit '%d'", $testId, $hwGroupMeta->getWallTimePerTest()));
      }
    }

    if ($cpuTimeSum > $hwGroupMeta->getCpuTimePerExercise()) {
      throw new ExerciseConfigException("Sum of the CPU time limits exceeds defined limit per exercise '{$hwGroupMeta->getCpuTimePerExercise()}'");
    }

    if ($wallTimeSum > $hwGroupMeta->getWallTimePerExercise()) {
      throw new ExerciseConfigException("Sum of the wall time limits exceeds defined limit per exercise '{$hwGroupMeta->getWallTimePerExercise()}'");
    }
  }

}
