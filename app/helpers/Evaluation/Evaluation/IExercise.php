<?php

namespace App\Helpers\Evaluation;


/**
 * Interface defining operations which exercise and its instance (assignment)
 * should comply. It includes methods needed for compiling exercise
 * configuration to backend format and also methods for proper evaluation.
 */
interface IExercise {

  /**
   * Get score calculator specific for this exercise.
   * @return null|string
   */
  function getScoreCalculator(): ?string;

  /**
   * Get score configuration which will be used within exercise calculator.
   * @return string
   */
  function getScoreConfig(): string;

}
