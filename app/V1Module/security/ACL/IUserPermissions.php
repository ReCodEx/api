<?php
namespace App\Security\ACL;


use App\Model\Entity\User;

interface IUserPermissions {
  function canViewAll(): bool;
  function canViewList(): bool;
  function canCreate(): bool;
  function canViewPublicData(User $user): bool;
  function canViewDetail(User $user): bool;
  function canUpdateProfile(User $user): bool;
  function canViewExercises(User $user): bool;
  function canViewGroups(User $user): bool;
  function canViewInstances(User $user): bool;
  function canDelete(User $user): bool;
  function canTakeOver(User $user): bool;
  function canCreateLocalAccount(User $user): bool;
  function canUpdatePersonalData(User $user): bool;
  function canSetRole(User $user): bool;
  function canSetIsAllowed(User $user): bool;
  function canInvalidateTokens(User $user): bool;
  function canForceChangePassword(User $user): bool;
}
