<?php

namespace App\Helpers\EvaluationStatus;

class EvaluationStatus {

  const EVALUATION_STATUS_IN_PROGRESS = "work-in-progress";
  const EVALUATION_STATUS_EVALUATION_FAILED = "evaluation-failed";
  const EVALUATION_STATUS_DONE = "done";
  const EVALUATION_STATUS_FAILED = "failed";
  
  public static function getStatus(IEvaluable $evaluable) {
      if (!$evaluable->canBeEvaluated()) {
        return self::EVALUATION_STATUS_EVALUATION_FAILED;
      } else if (!$evaluable->hasEvaluation()) {
        return self::EVALUATION_STATUS_EVALUATION_FAILED;
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
