<?php
namespace App\Security\Policies;


use App\Model\Entity\Submission;
use App\Security\Identity;

class SubmissionPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return Submission::class;
  }

  public function isPublic(Identity $identity, Submission $submission) {
    return $submission->isPublic();
  }

  public function isSupervisor(Identity $identity, Submission $submission) {
    $assignment = $submission->getAssignment();
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    return $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isAuthor(Identity $identity, Submission $submission) {
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    return $user === $submission->getUser();
  }

  public function areEvaluationDetailsPublic(Identity $identity, Submission $submission) {
    return $submission->getAssignment()->getCanViewLimitRatios();
  }
}