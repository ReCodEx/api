<?php

namespace App\Model\View\Helpers;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Exercise;
use App\Security\ACL\IAssignmentSolutionPermissions;
use Nette\SmartObject;

/**
 * Structure that holds all options controlling submission view construction.
 */
class SubmissionViewOptions
{
    use SmartObject;

    private $details = true;
    private $values = true;
    private $judgeStdout = true;
    private $judgeStderr = true;
    private $mergeJudgeLogs = true;

    /**
     * Initialize the options for given assignment solution and using ACL permissions.
     * @param AssignmentSolution $solution
     * @param IAssignmentSolutionPermissions $permissions
     */
    public function initializeAssignment(
        AssignmentSolution $solution,
        IAssignmentSolutionPermissions $permissions
    ): void {
        $this->details = $permissions->canViewEvaluationDetails($solution);
        $this->values = $permissions->canViewEvaluationValues($solution);
        $this->judgeStdout = $permissions->canViewEvaluationJudgeStdout($solution);
        $this->judgeStderr = $permissions->canViewEvaluationJudgeStderr($solution);
        $assignment = $solution->getAssignment();
        if ($assignment) {
            $this->mergeJudgeLogs = $assignment->getMergeJudgeLogs();
        }
    }

    /**
     * Initialize the options for given exercise reference solution.
     * @param Exercise $exercise
     */
    public function initializeExercise(Exercise $exercise): void
    {
        $this->details = true;
        $this->values = true;
        $this->judgeStdout = true;
        $this->judgeStderr = true;
        $this->mergeJudgeLogs = $exercise->getMergeJudgeLogs();
    }

    public function canViewDetails(): bool
    {
        return $this->details;
    }

    public function canViewValues(): bool
    {
        return $this->values;
    }

    public function canViewJudgeStdout(): bool
    {
        return $this->judgeStdout;
    }

    public function canViewJudgeStderr(): bool
    {
        return $this->judgeStderr;
    }

    public function mergeJudgeLogs(): bool
    {
        return $this->mergeJudgeLogs;
    }
}
