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

}
