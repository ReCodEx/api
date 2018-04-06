<?php
namespace App\Security\Policies;


use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Security\Identity;

class UserPermissionPolicy implements IPermissionPolicy
{
  public function getAssociatedClass() {
    return User::class;
  }

  public function isSameUser(Identity $identity, User $user): bool {
    $currentUser = $identity->getUserData();
    return $currentUser !== null && $currentUser === $user;
  }

  public function isInSameInstance(Identity $identity, User $user): bool {
    $currentUser = $identity->getUserData();
    return $currentUser !== null && $currentUser->getInstance() === $user->getInstance();
  }

  public function isNotExternalAccount(Identity $identity, User $user): bool {
    $currentUser = $identity->getUserData();
    if (!$currentUser) {
      return false;
    }

    return !$user->hasExternalAccounts();
  }

  public function isSupervisor(Identity $identity, User $user) {
    $currentUser = $identity->getUserData();
    if (!$currentUser) {
      return false;
    }

    return $user->getRole() === User::SUPERVISOR_ROLE;
  }

}
