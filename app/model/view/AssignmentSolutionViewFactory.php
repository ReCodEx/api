<?php

namespace App\Model\View;

/*
use App\Helpers\EvaluationStatus\EvaluationStatus;
use App\Model\Entity\Assignment;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use Doctrine\Common\Collections\Collection;
*/
use App\Model\Repository\Comments;
use App\Model\Entity\AssignmentSolution;
use App\Security\ACL\IAssignmentSolutionPermissions;

/**
 * Factory for group views which somehow do not fit into json serialization of
 * entities.
 */
class AssignmentSolutionViewFactory {

  /**
   * @var IAssignmentSolutionPermissions
   * @inject
   */
  public $assignmentSolutionAcl;

  /**
   * @var Comments
   */
  private $comments;

  public function __construct(IAssignmentSolutionPermissions $assignmentSolutionAcl, Comments $comments) {
    $this->assignmentSolutionAcl = $assignmentSolutionAcl;
    $this->comments = $comments;
  }

  /**
   * Parametrized view.
   * @param AssignmentSolution $solution
   * @return array
   */
  public function getSolutionData(AssignmentSolution $solution) {
    // Get permission details
    $canViewDetails = $this->assignmentSolutionAcl->canViewEvaluationDetails($solution);
    $canViewValues = $this->assignmentSolutionAcl->canViewEvaluationValues($solution);
    $canViewResubmissions = $this->assignmentSolutionAcl->canViewResubmissions($solution);

    $lastSubmissionId = $solution->getLastSubmission() ? $solution->getLastSubmission()->getId() : null;
    $lastSubmissionIdArray = $lastSubmissionId ? [ $lastSubmissionId ] : [];
    $submissions = $canViewResubmissions ? $solution->getSubmissionsIds() : $lastSubmissionIdArray;

    return [
      "id" => $solution->getId(),
      "note" => $solution->getNote(),
      "exerciseAssignmentId" => $solution->getAssignment()->getId(),
      "solution" => $solution->getSolution(),
      "runtimeEnvironmentId" => $solution->getSolution()->getRuntimeEnvironment()->getId(),
      "maxPoints" => $solution->getMaxPoints(),
      "accepted" => $solution->getAccepted(),
      "bonusPoints" => $solution->getBonusPoints(),
      "lastSubmission" => $solution->getLastSubmission() ? $solution->getLastSubmission()->getData($canViewDetails, $canViewValues) : null,
      "submissions" => $submissions,
    ];
  }
}
