<?php
namespace App\Security\Policies;

use App\Model\Entity\ShadowAssignmentPoints;
use App\Security\Identity;

class ShadowAssignmentPointsPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return ShadowAssignmentPoints::class;
  }

  public function isSupervisor(Identity $identity, ShadowAssignmentPoints $points) {
    $assignment = $points->getShadowAssignment();
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === null) {
      return false;
    }

    return $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isAwardee(Identity $identity, ShadowAssignmentPoints $points) {
    $user = $identity->getUserData();
    if ($user === null) {
      return false;
    }

    return $points->getAwardee() === $user;
  }

}
