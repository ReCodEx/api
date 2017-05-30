<?php
namespace App\Security\Policies;


use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Security\Identity;

class UserPermissionPolicy implements IPermissionPolicy
{
  /** @var Users */
  private $users;

  public function __construct(Users $users)
  {
    $this->users = $users;
  }

  function getByID($id)
  {
    return $this->users->get($id);
  }

  public function isSameUser(Identity $identity, User $user): bool
  {
    $currentUser = $identity->getUserData();
    return $currentUser !== NULL && $currentUser === $user;
  }
}