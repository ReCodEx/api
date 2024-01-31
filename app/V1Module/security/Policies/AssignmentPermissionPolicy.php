<?php

namespace App\Security\Policies;

use App\Model\Entity\Assignment;
use App\Security\Identity;
use App\Helpers\SubmissionConfigHelper;
use DateTime;

class AssignmentPermissionPolicy implements IPermissionPolicy
{
    /** @var SubmissionConfigHelper */
    private $submissionHelper;

    public function __construct(SubmissionConfigHelper $submissionHelper)
    {
        $this->submissionHelper = $submissionHelper;
    }

    public function acceptsSubmissions(Identity $identity, Assignment $assignment)
    {
        return !$this->submissionHelper->isLocked();
    }

    public function getAssociatedClass()
    {
        return Assignment::class;
    }

    public function isPublic(Identity $identity, Assignment $assignment)
    {
        return $assignment->isPublic();
    }

    public function isVisible(Identity $identity, Assignment $assignment)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        $now = new DateTime();
        $group = $assignment->getGroup();
        if (!$group) {
            return false;
        }

        $visibleFromOk = $assignment->getVisibleFrom() === null || $assignment->getVisibleFrom() <= $now;
        return $assignment->isPublic() && $visibleFromOk;
    }

    public function isInActiveGroup(Identity $identity, Assignment $assignment)
    {
        $group = $assignment->getGroup();
        return $group && !$group->isArchived(); // active = not deleted and not archived
    }

    public function isAssignee(Identity $identity, Assignment $assignment)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return $assignment->getGroup() && $assignment->getGroup()->isMemberOf($user);
    }

    public function isSupervisorOrAdmin(Identity $identity, Assignment $assignment)
    {
        $group = $assignment->getGroup();
        $user = $identity->getUserData();

        if ($user === null) {
            return false;
        }

        return $group && ($group->isSupervisorOf($user) || $group->isAdminOf($user));
    }

    public function isObserverOrBetter(Identity $identity, Assignment $assignment)
    {
        $group = $assignment->getGroup();
        $user = $identity->getUserData();

        if ($user === null) {
            return false;
        }

        return $group && ($group->isObserverOf($user) || $group->isSupervisorOf($user) || $group->isAdminOf($user));
    }

    /**
     * Current user is either not locked at all, or locked to this group (where the assignment is).
     */
    public function userIsNotLockedElsewhere(Identity $identity, Assignment $assignment): bool
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
    public function isExamNotInProgress(Identity $identity, Assignment $assignment): bool
    {
        $group = $assignment->getGroup();
        $now = new DateTime();
        return $group && (!$group->hasExamPeriodSet() || $group->getExamEnd() < $now);
    }

    /**
     * The assignment is for an exam in progress and the student is already locked in the group.
     */
    public function isExamInProgressAndStudentLocked(Identity $identity, Assignment $assignment): bool
    {
        $user = $identity->getUserData();
        $group = $assignment->getGroup();
        $now = new DateTime();
        return $group && $group->hasExamPeriodSet() && $group->getExamBegin() <= $now && $now <= $group->getExamEnd()
            && $user->getGroupLock()->getId() === $group->getId();
    }
}
