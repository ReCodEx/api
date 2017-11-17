<?php
namespace App\Security\Policies;


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
}
