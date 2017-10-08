<?php
namespace App\Security\Policies;


use App\Model\Entity\Exercise;
use App\Security\Identity;

class ExercisePermissionPolicy implements IPermissionPolicy {

  public function getAssociatedClass() {
    return Exercise::class;
  }

  public function isAuthor(Identity $identity, Exercise $exercise) {
    $user = $identity->getUserData();
    if ($user === NULL) {
      return FALSE;
    }

    return $user === $exercise->getAuthor();
  }

  public function isSubGroupSupervisor(Identity $identity, Exercise $exercise) {
    $user = $identity->getUserData();
    $group = $exercise->getGroup();

    if ($user === NULL || $group === NULL || $exercise->isPublic() === FALSE) {
      return FALSE;
    }

    return $group->isAdminOrSupervisorOfSubgroup($user);
  }

  public function isPublic(Identity $identity, Exercise $exercise) {
    return $exercise->isPublic() && $exercise->getGroup() === NULL;
  }
}
