<?php

namespace App\Security\Policies;

use App\Model\Entity\ShadowAssignment;
use App\Security\Identity;
use DateTime;

class ShadowAssignmentPermissionPolicy implements IPermissionPolicy
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
        $group = $assignment->getGroup();
        $user = $identity->getUserData();

        if ($user === null) {
            return false;
        }

        return $group && ($group->isSupervisorOf($user) || $group->isAdminOf($user));
    }

    /**
     * Current user is either not locked at all, or locked to this group (where the assignment is).
     */
    public function userIsNotLockedElsewhere(Identity $identity, ShadowAssignment $assignment): bool
    {
        $user = $identity->getUserData();
        $group = $assignment->getGroup();
        if ($user === null || $group === null || $group->isArchived()) {
            return false;
        }

        return !$user->isGroupLocked() || $user->getGroupLock()->getId() === $group->getId();
    }

    /**
     * The assignment is not in an exam group, or it is already after the exam.
     */
    public function isNotForExam(Identity $identity, ShadowAssignment $assignment): bool
    {
        $group = $assignment->getGroup();
        $now = new DateTime();
        return $group && (!$group->hasExamPeriodSet() || $group->getExamEnd() < $now);
    }

    /**
     * The assignment is for an exam in progress and the student is already locked in the group.
     */
    public function isExamInProgressAndStudentLocked(Identity $identity, ShadowAssignment $assignment): bool
    {
        $user = $identity->getUserData();
        $group = $assignment->getGroup();
        $now = new DateTime();
        return $group && $group->hasExamPeriodSet() && $group->getExamBegin() <= $now && $now <= $group->getExamEnd()
            && $user->getGroupLock()->getId() === $group->getId();
    }
}
