<?php

namespace App\Security\Policies;

use App\Model\Entity\Group;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Security\Identity;

class ReferenceExerciseSolutionPermissionPolicy implements IPermissionPolicy
{
    /** @var ExercisePermissionPolicy */
    private $exercisePermissionPolicy;

    public function __construct(ExercisePermissionPolicy $exercisePermissionPolicy)
    {
        $this->exercisePermissionPolicy = $exercisePermissionPolicy;
    }

    public function getAssociatedClass()
    {
        return ReferenceExerciseSolution::class;
    }

    public function isAuthor(Identity $identity, ReferenceExerciseSolution $referenceExerciseSolution = null)
    {
        if ($referenceExerciseSolution === null) {
            return false;
        }

        $user = $identity->getUserData();

        if ($user === null) {
            return false;
        }

        return $user === $referenceExerciseSolution->getSolution()->getAuthor();
    }

    public function isExerciseAuthorOrAdmin(Identity $identity, ReferenceExerciseSolution $referenceExerciseSolution)
    {
        $user = $identity->getUserData();
        if ($user === null || $referenceExerciseSolution->getExercise() === null) {
            return false;
        }

        $exercise = $referenceExerciseSolution->getExercise();
        return $user === $exercise->getAuthor() || $exercise->getAdmins()->contains($user);
    }

    public function isExerciseSuperGroupAdmin(Identity $identity, ReferenceExerciseSolution $referenceExerciseSolution)
    {
        $user = $identity->getUserData();
        $exercise = $referenceExerciseSolution->getExercise();

        if (
            $user === null || $exercise === null ||
            $exercise->getGroups()->isEmpty() ||
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

    public function isExerciseSubGroupNonStudentMember(
        Identity $identity,
        ReferenceExerciseSolution $referenceExerciseSolution
    ) {
        $exercise = $referenceExerciseSolution->getExercise();
        return $exercise !== null && $this->exercisePermissionPolicy->isSubGroupNonStudentMember($identity, $exercise);
    }

    public function isExerciseNotArchived(
        Identity $identity,
        ReferenceExerciseSolution $referenceExerciseSolution = null
    ) {
        if ($referenceExerciseSolution === null) {
            return false;
        }

        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        $exercise = $referenceExerciseSolution->getExercise();
        return $exercise && !$exercise->isArchived();
    }

    public function isPublic(Identity $identity, ReferenceExerciseSolution $referenceExerciseSolution = null)
    {
        return $referenceExerciseSolution !== null
            && $referenceExerciseSolution->getVisibility() >= ReferenceExerciseSolution::VISIBILITY_PUBLIC;
    }
}
