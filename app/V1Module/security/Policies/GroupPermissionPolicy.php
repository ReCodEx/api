<?php

namespace App\Security\Policies;

use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Repository\Groups;
use App\Security\Identity;
use DateTIme;

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

        return $group->isMemberOf($user) || $group->isAdminOf($user);
    }

    public function isSupervisorOrAdmin(Identity $identity, Group $group): bool
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        return $group->isSupervisorOf($user) || $group->isAdminOf($user);
    }

    public function isObserver(Identity $identity, Group $group): bool
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        return $group->isObserverOf($user);
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

    public function isNotDetainingStudents(Identity $identity, Group $group): bool
    {
        return !$group->isDetaining();
    }

    public function isNotArchived(Identity $identity, Group $group): bool
    {
        return !$group->isArchived();
    }

    public function isNotOrganizational(Identity $identity, Group $group): bool
    {
        return !$group->isOrganizational();
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

    public function isNotExam(Identity $identity, Group $group): bool
    {
        return !$group->isExam();
    }

    public function isBeforeExam(Identity $identity, Group $group): bool
    {
        $now = new DateTime();
        return $group->isExam() && $now < $group->getExamBegin();
    }

    public function isExamInProgress(Identity $identity, Group $group): bool
    {
        $now = new DateTime();
        return $group->isExam() && $group->getExamBegin() <= $now && $now <= $group->getExamEnd();
    }

    public function isExamOver(Identity $identity, Group $group): bool
    {
        $now = new DateTime();
        return $group->isExam() && $group->getExamEnd() < $now;
    }

    /**
     * Current user is locked to the selected group.
     */
    public function userIsLockedInThisGroup(Identity $identity, Group $group): bool
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return $user->getGroupLock()?->getId() === $group->getId();
    }

    /**
     * Current user is either not locked at all, or locked to this group.
     */
    public function userIsNotLockedElsewhere(Identity $identity, Group $group): bool
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return !$user->isGroupLocked() || $user->getGroupLock()->getId() === $group->getId();
    }
}
