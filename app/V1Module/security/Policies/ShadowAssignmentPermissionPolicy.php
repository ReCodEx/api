<?php
namespace App\Security\Policies;

use App\Model\Entity\ShadowAssignment;
use App\Security\Identity;

class ShadowAssignmentPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return ShadowAssignment::class;
  }

  public function isPublic(Identity $identity, ShadowAssignment $assignment) {
    return $assignment->isPublic();
  }

  public function isAssignee(Identity $identity, ShadowAssignment $assignment) {
    $user = $identity->getUserData();

    if ($user === null) {
      return false;
    }

    return $assignment->getGroup() && $assignment->getGroup()->isMemberOf($user);
  }

  public function isSupervisor(Identity $identity, ShadowAssignment $assignment) {
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === null) {
      return false;
    }

    return $group && $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

}
