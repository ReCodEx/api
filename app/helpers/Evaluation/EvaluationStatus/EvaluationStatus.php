<?php

namespace App\Helpers\EvaluationStatus;

/**
 * Helper class for manipulation with evaluations
 */
class EvaluationStatus
{
    private const EVALUATION_STATUS_IN_PROGRESS = "work-in-progress";
    private const EVALUATION_STATUS_EVALUATION_FAILED = "evaluation-failed";
    private const EVALUATION_STATUS_DONE = "done";
    private const EVALUATION_STATUS_FAILED = "failed";

    /**
     * Helper method for converting IEvaluable object to human readable string status
     * @param IEvaluable $evaluable Object which state will be returned
     * @return string String representation of given object
     */
    public static function getStatus(IEvaluable $evaluable): string
    {
        if ($evaluable->isFailed()) {
            return self::EVALUATION_STATUS_EVALUATION_FAILED;
        }

        if (!$evaluable->hasEvaluation()) {
            return self::EVALUATION_STATUS_IN_PROGRESS;
        }

        return $evaluable->isCorrect() ? self::EVALUATION_STATUS_DONE : self::EVALUATION_STATUS_FAILED;
    }
}
