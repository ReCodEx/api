<?php
namespace App\Security\Policies;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Comment;
use App\Model\Repository\AssignmentSolutions;
use App\Security\Identity;

class CommentPermissionPolicy implements IPermissionPolicy {
  private $assignmentSolutions;

  public function __construct(AssignmentSolutions $assignmentSolutions) {
    $this->assignmentSolutions = $assignmentSolutions;
  }

  function getAssociatedClass() {
    return Comment::class;
  }

  public function isAuthor(Identity $identity, Comment $comment) {
    $user = $identity->getUserData();
    if (!$user) {
      return false;
    }

    return $user === $comment->getUser();
  }

  public function isSolutionComment(Identity $identity, Comment $comment) {
    $user = $identity->getUserData();
    if (!$user) {
      return false;
    }

    $solution = $this->assignmentSolutions->get($comment->getCommentThread()->getId());
    return $solution !== null;
  }

  public function isSupervisorInGroupOfCommentedSolution(Identity $identity, Comment $comment) {
    $user = $identity->getUserData();
    if (!$user) {
      return false;
    }

    /** @var AssignmentSolution $solution */
    $solution = $this->assignmentSolutions->get($comment->getCommentThread()->getId());
    if ($solution === null) {
      return false;
    }

    $group = $solution->getAssignment()->getGroup();
    return $group && $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }
}
