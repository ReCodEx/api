<?php

namespace App\Helpers\EvaluationStatus;

use App\Model\Entity\SolutionEvaluation;

/**
 * Interface for every object, which can be evaluated
 */
interface IEvaluable
{

    /**
     * Query if the evaluation is ready
     * @return boolean The result
     */
    public function hasEvaluation(): bool;

    /**
     * Get the evaluation
     * @return SolutionEvaluation|null The evaluation
     */
    public function getEvaluation(): ?SolutionEvaluation;

    /**
     * Query if evaluation is possible (it might not be possible e.g. if the backend rejected the request)
     * @return boolean The result
     */
    public function canBeEvaluated(): bool;

    /**
     * Return true if the evaluation failed
     * @return bool
     */
    public function isFailed(): bool;

    /**
     * Return true if the evaluated object was marked as correct by the backend
     * @return bool
     */
    public function isCorrect(): bool;
}
