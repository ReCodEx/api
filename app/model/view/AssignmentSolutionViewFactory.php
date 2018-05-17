<?php

namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Repository\Comments;
use App\Model\Entity\AssignmentSolution;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Security\UserStorage;

/**
 * Factory for solution views which somehow do not fit into json serialization of entities.
 */
class AssignmentSolutionViewFactory {

  /**
   * @var IAssignmentSolutionPermissions
   */
  public $assignmentSolutionAcl;

  /**
   * @var Comments
   */
  private $comments;

  /**
   * @var UserStorage
   */
  private $userStorage;

  /**
   * @var AssignmentSolutionSubmissionViewFactory
   */
  private $submissionViewFactory;

  public function __construct(IAssignmentSolutionPermissions $assignmentSolutionAcl, Comments $comments,
      UserStorage $userStorage, AssignmentSolutionSubmissionViewFactory $submissionViewFactory) {
    $this->assignmentSolutionAcl = $assignmentSolutionAcl;
    $this->comments = $comments;
    $this->userStorage = $userStorage;
    $this->submissionViewFactory = $submissionViewFactory;
  }


  /**
   * Parametrized view.
   * @param AssignmentSolution $solution
   * @return array
   */
  public function getSolutionData(AssignmentSolution $solution) {
    // Get permission details
    $canViewResubmissions = $this->assignmentSolutionAcl->canViewResubmissions($solution);

    $lastSubmissionId = $solution->getLastSubmission() ? $solution->getLastSubmission()->getId() : null;
    $lastSubmissionIdArray = $lastSubmissionId ? [ $lastSubmissionId ] : [];
    $submissions = $canViewResubmissions ? $solution->getSubmissionsIds() : $lastSubmissionIdArray;

    $lastSubmission = !$solution->getLastSubmission() ? null :
      $this->submissionViewFactory->getSubmissionData($solution->getLastSubmission());

    $thread = $this->comments->getThread($solution->getId());
    $user = $this->userStorage->getUserData();
    $threadCommentsCount = ($thread && $user) ? $this->comments->getThreadCommentsCount($thread, $user) : 0;

    return [
      "id" => $solution->getId(),
      "note" => $solution->getNote(),
      "exerciseAssignmentId" => $solution->getAssignment()->getId(),
      "solution" => $solution->getSolution(),
      "runtimeEnvironmentId" => $solution->getSolution()->getRuntimeEnvironment()->getId(),
      "maxPoints" => $solution->getMaxPoints(),
      "accepted" => $solution->getAccepted(),
      "bonusPoints" => $solution->getBonusPoints(),
      "lastSubmission" => $lastSubmission,
      "submissions" => $submissions,
      "commentsStats" => $threadCommentsCount ? [
        "count" => $threadCommentsCount,
        "authoredCount" => $this->comments->getAuthoredCommentsCount($thread, $user),
        "last" => $this->comments->getThreadLastComment($thread, $user),
        ] : null,
      "permissionHints" => PermissionHints::get($this->assignmentSolutionAcl, $solution)
    ];
  }
}
