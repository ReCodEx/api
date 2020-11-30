<?php

namespace App\Model\View;

use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\SubmissionFailure;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Helpers\EvaluationStatus\EvaluationStatus;

/**
 * Factory for assignment solution submission views.
 */
class AssignmentSolutionSubmissionViewFactory
{

    /**
     * @var IAssignmentSolutionPermissions
     */
    public $assignmentSolutionAcl;

    public function __construct(IAssignmentSolutionPermissions $assignmentSolutionAcl)
    {
        $this->assignmentSolutionAcl = $assignmentSolutionAcl;
    }


    /**
     * Parametrized view.
     * @param AssignmentSolutionSubmission $submission
     * @return array
     */
    public function getSubmissionData(AssignmentSolutionSubmission $submission)
    {
        // Get permission details
        $canViewDetails = $this->assignmentSolutionAcl->canViewEvaluationDetails($submission->getAssignmentSolution());
        $canViewValues = $this->assignmentSolutionAcl->canViewEvaluationValues($submission->getAssignmentSolution());
        $canViewJudgeStdout = $this->assignmentSolutionAcl->canViewEvaluationJudgeStdout(
            $submission->getAssignmentSolution()
        );
        $canViewJudgeStderr = $this->assignmentSolutionAcl->canViewEvaluationJudgeStderr(
            $submission->getAssignmentSolution()
        );

        $evaluationData = null;
        if ($submission->getEvaluation() !== null) {
            $evaluationData = $submission->getEvaluation()->getData(
                $canViewDetails,
                $canViewValues,
                $canViewJudgeStdout,
                $canViewJudgeStderr
            );
        }

        $failure = $submission->getFailure();
        if ($failure && $failure->isConfigErrorFailure()) {
            $failure = $failure->toSimpleArray();
        } else {
            $failure = null;
        }

        return [
            "id" => $submission->getId(),
            "assignmentSolutionId" => $submission->getAssignmentSolution()->getId(),
            "evaluationStatus" => EvaluationStatus::getStatus($submission),
            "isCorrect" => $submission->isCorrect(),
            "evaluation" => $evaluationData,
            "submittedAt" => $submission->getSubmittedAt()->getTimestamp(),
            "submittedBy" => $submission->getSubmittedBy() ? $submission->getSubmittedBy()->getId() : null,
            "isDebug" => $submission->isDebug(),
            "failure" => $failure,
        ];
    }
}
