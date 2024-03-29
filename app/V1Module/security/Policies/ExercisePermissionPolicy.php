<?php

namespace App\Security\Policies;

use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Helpers\SubmissionConfigHelper;
use App\Security\Identity;

class ExercisePermissionPolicy implements IPermissionPolicy
{
    /** @var SubmissionConfigHelper */
    private $submissionHelper;

    public function __construct(SubmissionConfigHelper $submissionHelper)
    {
        $this->submissionHelper = $submissionHelper;
    }

    public function acceptsSubmissions(Identity $identity, Exercise $exercise)
    {
        return !$this->submissionHelper->isLocked();
    }

    public function getAssociatedClass()
    {
        return Exercise::class;
    }

    public function notArchived(Identity $identity, Exercise $exercise)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return !$exercise->isArchived();
    }

    /**
     * This is possibly deprecated as admins should have the same right as the author.
     * However, let's keep it for now and we shall see whether this holds (or not) in the future.
     */
    public function isAuthor(Identity $identity, Exercise $exercise)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return $user === $exercise->getAuthor();
    }

    public function isAuthorOrAdmin(Identity $identity, Exercise $exercise)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return $user === $exercise->getAuthor() || $exercise->getAdmins()->contains($user);
    }


    public function isSubGroupSupervisor(Identity $identity, Exercise $exercise)
    {
        $user = $identity->getUserData();

        if (
            $user === null || $exercise->getGroups()->isEmpty() ||
            $exercise->isPublic() === false
        ) {
            return false;
        }

        /** @var Group $group */
        foreach ($exercise->getGroups() as $group) {
            if ($group->isAdminOrSupervisorOfSubgroup($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @var Array[]
     * A cache holding the result of isNonStudentMemberOfSubgroup invocation for given groups.
     * The cache is structures as [user-id][group-id] => boolean
     * Under normal circumstances, the cache should hold only one (logged in) user,
     * but it was written as generic cache just in case.
     */
    private $subgroupMembersCache = [];

    public function isSubGroupNonStudentMember(Identity $identity, Exercise $exercise)
    {
        $user = $identity->getUserData();

        if (
            $user === null || $exercise->getGroups()->isEmpty() ||
            $exercise->isPublic() === false
        ) {
            return false;
        }

        if (empty($this->subgroupMembersCache[$user->getId()])) {
            $this->subgroupMembersCache[$user->getId()] = [];
        }
        $subgroupCache = &$this->subgroupMembersCache[$user->getId()];

        /** @var Group $group */
        foreach ($exercise->getGroups() as $group) {
            if (!array_key_exists($group->getId(), $subgroupCache)) {
                $subgroupCache[$group->getId()] = $group->isNonStudentMemberOfSubgroup($user);
            }
            if ($subgroupCache[$group->getId()]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @var Array[]
     * A cache holding the result of isAdminOf invocation for given groups.
     * The cache is structures as [user-id][group-id] => boolean
     * Under normal circumstances, the cache should hold only one (logged in) user,
     * but it was written as generic cache just in case.
     */
    private $supergroupAdminCache = [];

    public function isSuperGroupAdmin(Identity $identity, Exercise $exercise)
    {
        $user = $identity->getUserData();

        if (
            $user === null || $exercise->getGroups()->isEmpty() ||
            $exercise->isPublic() === false
        ) {
            return false;
        }

        if (empty($this->supergroupAdminCache[$user->getId()])) {
            $this->supergroupAdminCache[$user->getId()] = [];
        }
        $supergroupCache = &$this->supergroupAdminCache[$user->getId()];

        /** @var Group $group */
        foreach ($exercise->getGroups() as $group) {
            if (!array_key_exists($group->getId(), $supergroupCache)) {
                $supergroupCache[$group->getId()] = $group->isAdminOf($user);
            }
            if ($supergroupCache[$group->getId()]) {
                return true;
            }
        }

        return false;
    }

    public function isGloballyPublic(Identity $identity, Exercise $exercise)
    {
        return $exercise->isPublic() && $exercise->getGroups()->isEmpty();
    }

    public function hasAtLeastTwoAttachedGroups(Identity $identity, Exercise $exercise)
    {
        return $exercise->getGroups()->count() > 1;
    }
}
