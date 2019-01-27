<?php
namespace App\Security\Policies;


use App\Model\Entity\Group;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Security\Identity;

class ReferenceExerciseSolutionPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return ReferenceExerciseSolution::class;
  }

  public function isAuthor(Identity $identity, ReferenceExerciseSolution $referenceExerciseSolution = null) {
    if ($referenceExerciseSolution === null) {
      return false;
    }

    $user = $identity->getUserData();

    if ($user === null) {
      return false;
    }

    return $user === $referenceExerciseSolution->getSolution()->getAuthor();
  }

  public function isExerciseAuthor(Identity $identity, ReferenceExerciseSolution $referenceExerciseSolution) {
    $user = $identity->getUserData();

    if ($user === null) {
      return false;
    }

    if ($referenceExerciseSolution->getExercise() === null) {
      return false;
    }

    return $user === $referenceExerciseSolution->getExercise()->getAuthor();
  }

  public function isExerciseSuperGroupAdmin(Identity $identity, ReferenceExerciseSolution $referenceExerciseSolution) {
    $user = $identity->getUserData();
    $exercise = $referenceExerciseSolution->getExercise();

    if ($user === null || $exercise === null ||
        $exercise->getGroups()->isEmpty() ||
        $exercise->isPublic() === false) {
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

  public function isExerciseSubGroupSupervisor(Identity $identity, ReferenceExerciseSolution $referenceExerciseSolution)
  {
    $user = $identity->getUserData();
    $exercise = $referenceExerciseSolution->getExercise();

    if ($user === null || $exercise === null ||
        $exercise->getGroups()->isEmpty() ||
        $exercise->isPublic() === false) {
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
}
