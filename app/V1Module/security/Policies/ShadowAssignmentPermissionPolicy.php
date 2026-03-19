<?php

namespace App\Security\Policies;

use App\Model\Entity\GroupMembership;
use App\Model\Entity\ShadowAssignment;
use App\Security\Identity;

class ShadowAssignmentPermissionPolicy extends BasePermissionPolicy implements IPermissionPolicy
{
    public function getAssociatedClass()
    {
        return ShadowAssignment::class;
    }

    public function isPublic(Identity $identity, ShadowAssignment $assignment)
    {
        return $assignment->isPublic();
    }

    public function isInActiveGroup(Identity $identity, ShadowAssignment $assignment)
    {
        $group = $assignment->getGroup();
        return $group && !$group->isArchived(); // active = not deleted and not archived
    }


    public function isAssignee(Identity $identity, ShadowAssignment $assignment)
    {
        $user = $identity->getUserData();

        if ($user === null) {
            return false;
        }

        return $assignment->getGroup() && $assignment->getGroup()->isMemberOf($user);
    }

    public function isSupervisor(Identity $identity, ShadowAssignment $assignment)
    {
        return $this->checkMinimalMembership(
            $identity->getUserData(),
            $assignment->getGroup(),
            GroupMembership::TYPE_SUPERVISOR
        );
    }

    /**
     * Current user is either not locked at all, or locked to this group (where the assignment is).
     */
    public function userIsNotLockedElsewhereStrictly(Identity $identity, ShadowAssignment $assignment): bool
    {
        $user = $identity->getUserData();
        $group = $assignment->getGroup();
        if ($user === null || $group === null) {
            return false;
        }

        return !$user->isGroupLocked() || $user->getGroupLock()->getId() === $group->getId()
            || !$user->isGroupLockStrict();
    }
}
