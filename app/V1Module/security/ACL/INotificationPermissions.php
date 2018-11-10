<?php

namespace App\Security\ACL;

use App\Model\Entity\Group;
use App\Model\Entity\Notification;

interface INotificationPermissions {
  function canViewAll(): bool;
  function canViewCurrent(): bool;
  function canViewDetail(Notification $notification): bool;
  function canCreate(): bool;
  function canCreateGlobal(): bool;
  function canAddGroup(Group $group): bool;
  function canUpdate(Notification $notification): bool;
  function canRemove(Notification $notification): bool;
}
