<?php

namespace App\Model\View;

use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Security\ACL\IUserPermissions;


/**
 * Factory for conditional user views which somehow do not fit into json
 * serialization of entities.
 */
class UserViewFactory {

  /**
   * @var IUserPermissions
   */
  public $userAcl;

  public function __construct(IUserPermissions $userAcl) {
    $this->userAcl = $userAcl;
  }


  /**
   * @param User $user
   * @param bool $canViewPrivate
   * @return array
   */
  private function getUserData(User $user, bool $canViewPrivate) {
    $privateData = null;
    if ($canViewPrivate) {
      $privateData = [
        "email" => $user->getEmail(),
        "instanceId" => $user->getInstance()->getId(),
        "role" => $user->getRole(),
        "groups" => [
          "studentOf" => $user->getGroupsAsStudent()->map(function (Group $group) { return $group->getId(); })->getValues(),
          "supervisorOf" => $user->getGroupsAsSupervisor()->map(function (Group $group) { return $group->getId(); })->getValues()
        ],
        "settings" => $user->getSettings(),
        "isLocal" => $user->hasLocalAccounts(),
        "isExternal" => $user->getLogins()->isEmpty()
      ];
    }

    return [
      'id' => $user->getId(),
      'fullName' => $user->getName(),
      'name' => $user->getNameParts(),
      'avatarUrl' => $user->getAvatarUrl(),
      'isVerified' => $user->isVerified(),
      'privateData' => $privateData
    ];
  }

  /**
   * Get all information about user even private ones.
   * @param User $user
   * @return array
   */
  public function getFullUser(User $user) {
    return $this->getUserData($user, true);
  }

  /**
   * Get as much user detail info as your permissions grants you.
   * @param User $user
   * @return array User detail
   */
  public function getUser(User $user): array {
    return $this->getUserData($user, $this->userAcl->canViewDetail($user));
  }

  /**
   * Get user information about given students.
   * @param User[] $users
   * @return array
   */
  public function getUsers(array $users): array {
    return array_map(function (User $user) {
      return $this->getUser($user);
    }, $users);
  }

}
