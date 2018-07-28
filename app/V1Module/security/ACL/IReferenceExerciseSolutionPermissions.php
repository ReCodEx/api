<?php
namespace App\Security\ACL;

use App\Model\Entity\ReferenceExerciseSolution;

interface IReferenceExerciseSolutionPermissions {
  function canDelete(ReferenceExerciseSolution $referenceExerciseSolution): bool;
  function canEvaluate(ReferenceExerciseSolution $referenceExerciseSolution): bool;
  function canDeleteEvaluation(ReferenceExerciseSolution $referenceExerciseSolution): bool;
}
