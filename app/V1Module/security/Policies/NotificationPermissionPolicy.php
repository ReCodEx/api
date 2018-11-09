<?php

namespace App\Security\Policies;

use App\Model\Entity\Notification;
use App\Security\Authorizator;
use App\Security\Identity;

class NotificationPermissionPolicy implements IPermissionPolicy {

  /** @var Authorizator */
  private $authorizator;

  public function getAssociatedClass() {
    return Notification::class;
  }

  public function __construct(Authorizator $authorizator) {
    $this->authorizator = $authorizator;
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
