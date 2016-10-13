<?php

namespace App\Helpers\EvaluationStatus;

/**
 * Helper class for manipulation with evaluations 
 */
class EvaluationStatus {

  const EVALUATION_STATUS_IN_PROGRESS = "work-in-progress";
  const EVALUATION_STATUS_EVALUATION_FAILED = "evaluation-failed";
  const EVALUATION_STATUS_DONE = "done";
  const EVALUATION_STATUS_FAILED = "failed";
  
  /**
   * Helper method for converting IEvaluable object to human readable string status
   * @param IEvaluable $evaluable Object which state will be returned
   * @return string String representation of given object
   */
  public static function getStatus(IEvaluable $evaluable): string {
      if (!$evaluable->canBeEvaluated()) {
        return self::EVALUATION_STATUS_EVALUATION_FAILED;
      } else if (!$evaluable->hasEvaluation()) {
        return self::EVALUATION_STATUS_IN_PROGRESS;
      }

      $eval = $evaluable->getEvaluation();
      if ($eval->isValid() === FALSE) {
        return self::EVALUATION_STATUS_EVALUATION_FAILED;
      } elseif ($eval->isCorrect() === TRUE) {
        return self::EVALUATION_STATUS_DONE;
      } else {
        return self::EVALUATION_STATUS_FAILED;
      }
  }

}
