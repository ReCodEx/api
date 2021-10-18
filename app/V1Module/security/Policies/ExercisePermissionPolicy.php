<?php

namespace App\Security\Policies;

use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Security\Identity;

class ExercisePermissionPolicy implements IPermissionPolicy
{

    public function getAssociatedClass()
    {
        return Exercise::class;
    }

    public function isAuthor(Identity $identity, Exercise $exercise)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return $user === $exercise->getAuthor();
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

    public function isSubGroupNonStudentMember(Identity $identity, Exercise $exercise)
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
            if ($group->isNonStudentMemberOfSubgroup($user)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperGroupAdmin(Identity $identity, Exercise $exercise)
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
            if ($group->isAdminOf($user)) {
                return true;
            }
        }

        return false;
    }

    public function isPublic(Identity $identity, Exercise $exercise)
    {
        return $exercise->isPublic() && $exercise->getGroups()->isEmpty();
    }

    public function hasAtLeastTwoAttachedGroups(Identity $identity, Exercise $exercise)
    {
        return $exercise->getGroups()->count() > 1;
    }
}
