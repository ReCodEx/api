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
    return $currentUser !== NULL && $currentUser === $user;
  }

  public function isInSameInstance(Identity $identity, User $user): bool {
    $currentUser = $identity->getUserData();
    return $currentUser !== NULL && $currentUser->getInstance() === $user->getInstance();
  }
}