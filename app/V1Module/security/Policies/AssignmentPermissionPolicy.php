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
        $now = new DateTime();
        return $assignment->isPublic() &&
            ($assignment->getVisibleFrom() === null || $assignment->getVisibleFrom() <= $now);
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
}
