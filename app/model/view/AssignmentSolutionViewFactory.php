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

/**
 * Factory for group views which somehow do not fit into json serialization of
 * entities.
 */
class AssignmentSolutionViewFactory {

  /**
   * @var Comments
   */
  private $comments;

  public function __construct(Comments $comments) {
    $this->comments = $comments;
  }

  /**
   * Parametrized view.
   * @param bool $canViewRatios
   * @param bool $canViewValues
   * @param bool $canViewResubmissions
   * @return array
   */
  public function getSolutionData(AssignmentSolution $solution, $canViewRatios = false, bool $canViewValues = false, bool $canViewResubmissions = false) {
    $lastSubmissionId = $solution->getLastSubmission() ? $solution->getLastSubmission()->getId() : null;
    $lastSubmissionIdArray = $lastSubmissionId ? [ $lastSubmissionId ] : [];
    $submissions = $canViewResubmissions ? $solution->getSubmissionsIds() : $lastSubmissionIdArray;

    return [
      "id" => $solution->id,
      "note" => $solution->note,
      "exerciseAssignmentId" => $solution->assignment->getId(),
      "solution" => $solution->solution,
      "runtimeEnvironmentId" => $solution->solution->getRuntimeEnvironment()->getId(),
      "maxPoints" => $solution->getMaxPoints(),
      "accepted" => $solution->accepted,
      "bonusPoints" => $solution->bonusPoints,
      "lastSubmission" => $solution->getLastSubmission() ? $solution->getLastSubmission()->getData($canViewRatios, $canViewValues) : null,
      "submissions" => $submissions,
    ];
  }


}
