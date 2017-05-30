<?php
namespace App\Security\Policies;

use App\Model\Entity\Group;
use App\Model\Repository\Groups;
use App\Security\Identity;

class GroupPermissionPolicy implements IPermissionPolicy {
  /** @var Groups */
  private $groups;

  public function __construct(Groups $groups) {
    $this->groups = $groups;
  }

  public function getByID($id) {
    return $this->groups->get($id);
  }

  public function isGroupMember(Identity $identity, Group $group): bool {
    $user = $identity->getUserData();
    if (!$user) {
      return FALSE;
    }

    return $group->isMemberOf($user) || $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isGroupSupervisor(Identity $identity, Group $group): bool {
    $user = $identity->getUserData();
    if (!$user) {
      return FALSE;
    }

    return $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isGroupAdmin(Identity $identity, Group $group = NULL): bool {
    $user = $identity->getUserData();
    if (!$user) {
      return FALSE;
    }

    if (!$group) {
      $group = $user->getInstance()->getRootGroup();
    }

    return $group->isAdminOf($user);
  }

  public function isGroupPublic(Identity $identity, Group $group): bool {
    return $group->isPublic();
  }

  public function areGroupStatsPublic(Identity $identity, Group $group): bool {
    return $group->statsArePublic();
  }

  public function canAccessGroupDetail(Identity $identity, Group $group): bool {
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