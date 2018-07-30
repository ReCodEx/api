<?php
namespace App\Security\Policies;

use App\Model\Entity\ShadowAssignmentEvaluation;
use App\Security\Identity;

class ShadowAssignmentEvaluationPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return ShadowAssignmentEvaluation::class;
  }

  public function isSupervisor(Identity $identity, ShadowAssignmentEvaluation $evaluation) {
    $assignment = $evaluation->getShadowAssignment();
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === null) {
      return false;
    }

    return $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isEvaluatee(Identity $identity, ShadowAssignmentEvaluation $evaluation) {
    $user = $identity->getUserData();
    if ($user === null) {
      return false;
    }

    return $evaluation->getEvaluatee() === $user;
  }

}
