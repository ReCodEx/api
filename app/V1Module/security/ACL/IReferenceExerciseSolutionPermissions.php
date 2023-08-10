<?php

namespace App\Security\ACL;

use App\Model\Entity\ReferenceExerciseSolution;

interface IReferenceExerciseSolutionPermissions
{
    public function canViewDetail(ReferenceExerciseSolution $referenceExerciseSolution): bool;

    public function canDelete(ReferenceExerciseSolution $referenceExerciseSolution): bool;

    public function canEvaluate(ReferenceExerciseSolution $referenceExerciseSolution): bool;

    public function canDeleteEvaluation(ReferenceExerciseSolution $referenceExerciseSolution): bool;

    public function canSetVisibility(ReferenceExerciseSolution $referenceExerciseSolution): bool;

    public function canPromote(ReferenceExerciseSolution $referenceExerciseSolution): bool;
}
