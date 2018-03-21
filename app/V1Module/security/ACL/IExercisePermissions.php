<?php
namespace App\Security\ACL;


use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\ReferenceExerciseSolution;

interface IExercisePermissions {
  function canViewAll(): bool;
  function canViewDetail(Exercise $exercise): bool;
  function canUpdate(Exercise $exercise): bool;
  function canCreate(): bool;
  function canRemove(Exercise $exercise): bool;
  function canFork(Exercise $exercise): bool;
  function canViewLimits(Exercise $exercise): bool;
  function canSetLimits(Exercise $exercise): bool;
  function canViewScoreConfig(Exercise $exercise): bool;
  function canSetScoreConfig(Exercise $exercise): bool;
  function canAddReferenceSolution(Exercise $exercise): bool;
  function canDeleteReferenceSolution(Exercise $exercise, ?ReferenceExerciseSolution $referenceExerciseSolution): bool;
  function canEvaluateReferenceSolution(Exercise $exercise, ?ReferenceExerciseSolution $referenceExerciseSolution): bool;
  function canCreatePipeline(Exercise $exercise): bool;
  function canViewPipelines(Exercise $exercise): bool;
  function canAttachGroup(Exercise $exercise): bool;
  function canDetachGroup(Exercise $exercise): bool;
}
