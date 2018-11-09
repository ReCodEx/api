<?php

namespace App\Security\Policies;

use App\Model\Entity\Notification;
use App\Security\Identity;
use App\Security\Roles;

class NotificationPermissionPolicy implements IPermissionPolicy {

  /** @var Roles */
  private $roles;

  public function getAssociatedClass() {
    return Notification::class;
  }

  public function __construct(Roles $roles) {
    $this->roles = $roles;
  }


  public function hasRole(Identity $identity, Notification $notification) {
    $user = $identity->getUserData();
    if (!$user) {
      return false;
    }

    // TODO
  }

  public function isGlobal(Identity $identity, Notification $notification) {
    return $notification->getGroups()->isEmpty();
  }

  public function isGroupsMember(Identity $identity, Notification $notification) {
    $user = $identity->getUserData();
    if (!$user) {
      return false;
    }

    foreach ($notification->getGroups() as $group) {
      $isMember = $group->isMemberOfSubgroup($user);
      if ($isMember) {
        return true;
      }
    }

    return false;
  }
}
