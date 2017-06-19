<?php
namespace App\Security\Policies;

use App\Model\Entity\Group;
use App\Model\Repository\Groups;
use App\Security\Identity;

class GroupPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return Group::class;
  }

  public function isMember(Identity $identity, Group $group): bool {
    $user = $identity->getUserData();
    if (!$user) {
      return FALSE;
    }

    return $group->isMemberOf($user) || $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isSupervisor(Identity $identity, Group $group): bool {
    $user = $identity->getUserData();
    if (!$user) {
      return FALSE;
    }

    return $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isAdmin(Identity $identity, Group $group): bool {
    $user = $identity->getUserData();
    if (!$user) {
      return FALSE;
    }

    return $group->isAdminOf($user);
  }

  public function isPublic(Identity $identity, Group $group): bool {
    return $group->isPublic();
  }

  public function areStatsPublic(Identity $identity, Group $group): bool {
    return $group->statsArePublic();
  }

  public function canAccessDetail(Identity $identity, Group $group): bool {
    $user = $identity->getUserData();
    if ($user === NULL) {
      return FALSE;
    }

    if ($user->getInstance() !== $group->getInstance()) {
      return FALSE;
    }

    return $group->isMemberOf($user)
        || $group->isPublic()
        || ($user->getInstance() !== NULL
            && $user->getInstance()->getRootGroup() !== NULL
            && $group->getId() === $user->getInstance()->getRootGroup()->getId());
  }
}