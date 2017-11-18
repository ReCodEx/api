<?php
namespace App\Security\Policies;


use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Security\Identity;

class AssignmentSolutionPermissionPolicy implements IPermissionPolicy {

  public function getAssociatedClass() {
    return AssignmentSolution::class;
  }

  public function isSupervisor(Identity $identity, AssignmentSolution $solution,
      AssignmentSolutionSubmission $submission = null) {
    $assignment = $solution->getAssignment();
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    return $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isAuthor(Identity $identity, AssignmentSolution $solution,
      AssignmentSolutionSubmission $submission = null) {
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    if ($submission === null) {
      return $user === $solution->getSolution()->getAuthor();
    }

    return $user === $submission->getSubmittedBy();
  }

  public function areEvaluationDetailsPublic(Identity $identity, AssignmentSolution $solution,
      AssignmentSolutionSubmission $submission = null) {
    return $solution->getAssignment()->getCanViewLimitRatios();
  }
}
