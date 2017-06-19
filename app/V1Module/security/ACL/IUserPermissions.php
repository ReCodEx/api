<?php
namespace App\Security\ACL;


use App\Model\Entity\User;

interface IUserPermissions {
  function canViewAll(): bool;
  function canViewDetail(User $user): bool;
  function canUpdateProfile($user): bool;
  function canViewExercises($user): bool;
  function canViewGroups($user): bool;
  function canViewInstances($user): bool;
}