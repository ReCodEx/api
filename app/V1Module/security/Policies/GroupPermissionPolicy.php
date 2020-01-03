<?php

namespace App\Security\Policies;

use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Repository\Groups;
use App\Security\Identity;

class GroupPermissionPolicy implements IPermissionPolicy
{
    public function getAssociatedClass()
    {
        return Group::class;
    }

    public function isMember(Identity $identity, Group $group): bool
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        return $group->isMemberOf($user) || $group->isSupervisorOf($user) || $group->isAdminOf($user);
    }

    public function isSupervisor(Identity $identity, Group $group): bool
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        return $group->isSupervisorOf($user) || $group->isAdminOf($user);
    }

    public function isAdmin(Identity $identity, Group $group): bool
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        return $group->isAdminOf($user);
    }

    public function isPublic(Identity $identity, Group $group): bool
    {
        return $group->isPublic();
    }

    public function areStatsPublic(Identity $identity, Group $group): bool
    {
        return $group->statsArePublic();
    }

    public function isRootGroupOfInstance(Identity $identity, Group $group)
    {
        return $group->getInstance() && $group->getId() === $group->getInstance()->getId();
    }

    public function isInSameInstance(Identity $identity, Group $group): bool
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        if ($group->getInstance() === null) {
            return false;
        }

        return $user->getInstances()->exists(
            function ($key, Instance $instance) use ($group) {
                return $instance->getId() === $group->getInstance()->getId();
            }
        );
    }
}
