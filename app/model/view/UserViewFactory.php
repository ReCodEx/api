<?php

namespace App\Model\View;

use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use App\Security\ACL\IUserPermissions;
use App\Security\Identity;


/**
 * Factory for conditional user views which somehow do not fit into json
 * serialization of entities.
 */
class UserViewFactory {

  /**
   * @var IUserPermissions
   */
  public $userAcl;

  /**
   * @var Logins
   */
  public $logins;

  /** @var User */
  private $loggedInUser = null;

  public function __construct(IUserPermissions $userAcl, Logins $logins, \Nette\Security\User $user) {
    $this->userAcl = $userAcl;
    $this->logins = $logins;
    $identity = $user->getIdentity();
    if ($identity !== null && $identity instanceof Identity) {
      $this->loggedInUser = $identity->getUserData();
    }
 }

  /**
   * Get a structure with external IDs of an user, that the logged user may see.
   * @param User $user Who's external IDs are returned.
   * @return array
   */
  private function getExternalIds(User $user) {
    if (!$this->loggedInUser) {
      return [];
    }

    $filter = array_keys($this->loggedInUser->getConsolidatedExternalLogins());
    return $user->getConsolidatedExternalLogins($filter);
  }

  /**
   * @param User $user
   * @param bool $canViewPrivate
   * @return array
   */
  private function getUserData(User $user, bool $canViewPrivate) {
    $privateData = null;
    if ($canViewPrivate) {
      $login = $this->logins->findByUserId($user->getId());
      $emptyLocalPassword = $login ? $login->isPasswordEmpty() : true;

      $studentOf = $user->getGroupsAsStudent()->filter(function (Group $group) {
        return !$group->isArchived();
      });

      $supervisorOf = $user->getGroupsAsSupervisor()->filter(function (Group $group) {
        return !$group->isArchived();
      });

      $privateData = [
        "email" => $user->getEmail(),
        "createdAt" => $user->getCreatedAt()->getTimestamp(),
        "instancesIds" => $user->getInstancesIds(),
        "role" => $user->getRole(),
        "groups" => [
          "studentOf" => $studentOf->map(function (Group $group) { return $group->getId(); })->getValues(),
          "supervisorOf" => $supervisorOf->map(function (Group $group) { return $group->getId(); })->getValues()
        ],
        "settings" => $user->getSettings(),
        "emptyLocalPassword" => $emptyLocalPassword,
        "isLocal" => $user->hasLocalAccount(),
        "isExternal" => $user->hasExternalAccounts(),
        "isAllowed" => $user->isAllowed(),
        "externalIds" => $this->getExternalIds($user),
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
    $users = array_values($users);
    return array_map(function (User $user) {
      return $this->getUser($user);
    }, $users);
  }

}
