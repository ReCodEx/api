<?php

namespace App\Security\ACL;

use App\Model\Entity\Notification;

interface INotificationPermissions {
  function canViewAll(): bool;
  function canViewCurrent(): bool;
  function canViewDetail(Notification $notification): bool;
}
