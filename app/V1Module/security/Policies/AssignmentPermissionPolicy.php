<?php
namespace App\Security\Policies;

use App\Model\Entity\Assignment;
use App\Security\Identity;
use DateTime;

class AssignmentPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return Assignment::class;
  }

  public function isPublic(Identity $identity, Assignment $assignment) {
    return $assignment->isPublic();
  }

  public function isVisible(Identity $identity, Assignment $assignment) {
    $now = new DateTime();
    return $assignment->isPublic() &&
      ($assignment->getVisibleFrom() === null || $assignment->getVisibleFrom() <= $now);
  }

  public function isAssignee(Identity $identity, Assignment $assignment) {
    $user = $identity->getUserData();

    if ($user === null) {
      return false;
    }

    return $assignment->getGroup() && $assignment->getGroup()->isMemberOf($user);
  }

  public function isSupervisor(Identity $identity, Assignment $assignment) {
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === null) {
      return false;
    }

    return $group && $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

}
