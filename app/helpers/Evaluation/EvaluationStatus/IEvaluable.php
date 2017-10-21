<?php

namespace App\Helpers\EvaluationStatus;

use App\Model\Entity\SolutionEvaluation;

/**
 * Interface for every object, which can be evaluated
 */
interface IEvaluable {

  /**
   * Query if the evaluation is ready
   * @return boolean The result
   */
  function hasEvaluation(): bool;

  /**
   * Get the evaluation
   * @return SolutionEvaluation The evaluation
   */
  function getEvaluation(): SolutionEvaluation;

  /**
   * Query if evaluation is possible (it might not be possible e.g. if the backend rejected the request)
   * @return boolean The result
   */
  function canBeEvaluated(): bool;

  /**
   * Return true if the evaluation was finished successfully
   * @return bool
   */
  function isValid(): bool;

  /**
   * Return true if the evaluation failed
   * @return bool
   */
  function isFailed(): bool;

  /**
   * Return true if the evaluated object was marked as correct by the backend
   * @return bool
   */
  function isCorrect(): bool;

}
