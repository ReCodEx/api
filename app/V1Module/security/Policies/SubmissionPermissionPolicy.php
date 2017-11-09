<?php
namespace App\Security\Policies;


use App\Model\Entity\AssignmentSolution;
use App\Security\Identity;

class SubmissionPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return AssignmentSolution::class;
  }

  public function isSupervisor(Identity $identity, AssignmentSolution $submission) {
    $assignment = $submission->getAssignment();
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    return $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isAuthor(Identity $identity, AssignmentSolution $submission) {
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    return $user === $submission->getSolution()->getAuthor();
  }

  public function areEvaluationDetailsPublic(Identity $identity, AssignmentSolution $submission) {
    return $submission->getAssignment()->getCanViewLimitRatios();
  }
}
