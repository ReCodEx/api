<?php

namespace App\Model\View;

use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\SubmissionFailure;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Helpers\EvaluationStatus\EvaluationStatus;
use App\Model\View\Helpers\SubmissionViewOptions;

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
        $solution = $submission->getAssignmentSolution();

        $evaluationData = null;
        if ($submission->getEvaluation() !== null) {
            $viewOptions = new SubmissionViewOptions();
            $viewOptions->initializeAssignment($solution, $this->assignmentSolutionAcl);
            $evaluationData = $submission->getEvaluation()->getDataForView($viewOptions);
        }

        $failure = $submission->getFailure();
        $failure = $failure ? $failure->toSimpleArray() : null;

        return [
            "id" => $submission->getId(),
            "assignmentSolutionId" => $solution->getId(),
            "evaluation" => $evaluationData,
            "submittedAt" => $submission->getSubmittedAt()->getTimestamp(),
            "submittedBy" => $submission->getSubmittedBy() ? $submission->getSubmittedBy()->getId() : null,
            "isDebug" => $submission->isDebug(),
            "failure" => $failure,
        ];
    }
}
