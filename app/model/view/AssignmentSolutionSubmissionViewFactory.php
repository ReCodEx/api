<?php

namespace App\Model\View;

use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\SubmissionFailure;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Helpers\EvaluationStatus\EvaluationStatus;

/**
 * Factory for assignment solution submission views.
 */
class AssignmentSolutionSubmissionViewFactory {

  /**
   * @var IAssignmentSolutionPermissions
   */
  public $assignmentSolutionAcl;

  public function __construct(IAssignmentSolutionPermissions $assignmentSolutionAcl) {
    $this->assignmentSolutionAcl = $assignmentSolutionAcl;
  }


  /**
   * Parametrized view.
   * @param AssignmentSolutionSubmission $submission
   * @return array
   */
  public function getSubmissionData(AssignmentSolutionSubmission $submission) {
    // Get permission details
    $canViewDetails = $this->assignmentSolutionAcl->canViewEvaluationDetails($submission->getAssignmentSolution());
    $canViewValues = $this->assignmentSolutionAcl->canViewEvaluationValues($submission->getAssignmentSolution());
    $canViewJudgeOutput = $this->assignmentSolutionAcl->canViewEvaluationJudgeOutput($submission->getAssignmentSolution());

    $evaluationData = null;
    if ($submission->getEvaluation() !== null) {
      $evaluationData = $submission->getEvaluation()->getData($canViewDetails, $canViewValues, $canViewJudgeOutput);
    }

    $failures = $submission->getFailures()->filter(function (SubmissionFailure $failure) {
      return $failure->getType() === SubmissionFailure::TYPE_CONFIG_ERROR;
    })->map(function (SubmissionFailure $failure) {
      return $failure->toSimpleArray();
    })->toArray();

    return [
      "id" => $submission->getId(),
      "assignmentSolutionId" => $submission->getAssignmentSolution()->getId(),
      "evaluationStatus" => EvaluationStatus::getStatus($submission),
      "isCorrect" => $submission->isCorrect(),
      "evaluation" => $evaluationData,
      "submittedAt" => $submission->getSubmittedAt()->getTimestamp(),
      "submittedBy" => $submission->getSubmittedBy() ? $submission->getSubmittedBy()->getId() : null,
      "isDebug" => $submission->isDebug(),
      "failures" => $failures
    ];
  }
}
