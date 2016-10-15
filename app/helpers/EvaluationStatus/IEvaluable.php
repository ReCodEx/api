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
   * Query if evaluation is possible
   * @return boolean The result
   */
  function canBeEvaluated(): bool;

}
