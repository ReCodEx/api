<?php

namespace App\Security\Policies;

use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Security\Identity;
use App\Security\Roles;

class UserPermissionPolicy implements IPermissionPolicy
{
    public function getAssociatedClass()
    {
        return User::class;
    }

    public function isSameUser(Identity $identity, User $user): bool
    {
        $currentUser = $identity->getUserData();
        return $currentUser !== null && $currentUser === $user;
    }

    public function isInSameInstance(Identity $identity, User $user): bool
    {
        $currentUser = $identity->getUserData();
        if ($currentUser === null) {
            return false;
        }

        return $currentUser->getInstances()->exists(
            function ($key, Instance $instance) use ($user) {
                return $user->getInstances()->contains($instance);
            }
        );
    }

    public function isNotExternalAccount(Identity $identity, User $user): bool
    {
        $currentUser = $identity->getUserData();
        if (!$currentUser) {
            return false;
        }

        return !$user->hasExternalAccounts();
    }

    public function isSupervisor(Identity $identity, User $user)
    {
        $currentUser = $identity->getUserData();
        if (!$currentUser) {
            return false;
        }

        return $user->getRole() === Roles::SUPERVISOR_ROLE;
    }

    /**
     * Logged user is supervisor, observer, or admin of any group of which the tested user is member.
     */
    public function isReaderOfJoinedGroup(Identity $identity, User $user): bool
    {
        $currentUser = $identity->getUserData();
        if ($currentUser === null) {
            return false;
        }

        foreach ($user->getGroupsAsStudent() as $group) {
            if ($group->isSupervisorOf($currentUser) || $group->isObserverOf($currentUser) || $group->isAdminOf($currentUser)) {
                return true;
            }
        }
        return false;
    }
}
