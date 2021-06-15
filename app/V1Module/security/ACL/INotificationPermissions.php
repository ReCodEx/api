<?php

namespace App\Security\ACL;

use App\Model\Entity\Group;
use App\Model\Entity\Notification;

interface INotificationPermissions
{
    public function canViewAll(): bool;

    public function canViewCurrent(): bool;

    public function canViewDetail(Notification $notification): bool;

    public function canCreate(): bool;

    public function canCreateGlobal(): bool;

    public function canAddGroup(Group $group): bool;

    public function canUpdate(Notification $notification): bool;

    public function canRemove(Notification $notification): bool;
}
