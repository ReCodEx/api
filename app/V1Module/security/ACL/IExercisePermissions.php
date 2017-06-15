<?php
namespace App\Security\ACL;


use App\Model\Entity\Exercise;

interface IExercisePermissions {
  function canViewAll(): bool;
  function canViewDetail(Exercise $exercise): bool;
  function canUpdate(Exercise $exercise): bool;
  function canCreate(): bool;
  function canRemove(Exercise $exercise): bool;
  function canFork(Exercise $exercise): bool;
}