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
class UserViewFactory
{
    /**
     * @var IUserPermissions
     */
    public $userAcl;

    /**
     * @var Logins
     */
    public $logins;

    /** @var User|null */
    private $loggedInUser = null;

    public function __construct(IUserPermissions $userAcl, Logins $logins, \Nette\Security\User $user)
    {
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
     * @param bool $canViewAllExternalIds
     * @return array
     */
    private function getExternalIds(User $user, bool $canViewAllExternalIds = false)
    {
        if (!$canViewAllExternalIds) {
            if (!$this->loggedInUser) {
                return [];
            }
            $filter = array_keys($this->loggedInUser->getConsolidatedExternalLogins());
        } else {
            $filter = null; // no filterings
        }
        return $user->getConsolidatedExternalLogins($filter);
    }

    private function isUserLoggedInUser(User $user)
    {
        return $this->loggedInUser && $this->loggedInUser->getId() === $user->getId();
    }

    /**
     * @param User $user
     * @param bool $canViewPrivate
     * @param bool $reallyShowEverything
     * @return array
     */
    private function getUserData(User $user, bool $canViewPrivate, bool $reallyShowEverything = false)
    {
        $privateData = null;
        if ($canViewPrivate) {
            $login = $this->logins->findByUserId($user->getId());
            $emptyLocalPassword = $login ? $login->isPasswordEmpty() : true;

            $privateData = [
                "email" => $user->getEmail(),
                "createdAt" => $user->getCreatedAt()->getTimestamp(),
                "lastAuthenticationAt" => $user->getLastAuthenticationAt()
                    ? $user->getLastAuthenticationAt()->getTimestamp() : null,
                "instancesIds" => $user->getInstancesIds(),
                "role" => $user->getRole(),
                "emptyLocalPassword" => $emptyLocalPassword,
                "isLocal" => $user->hasLocalAccount(),
                "isExternal" => $user->hasExternalAccounts(),
                "isAllowed" => $user->isAllowed(),
                "externalIds" => $this->getExternalIds($user, $reallyShowEverything),
                "ipLock" => $user->isIpLocked(),
                "groupLock" => $user->getGroupLock()?->getId(),
                "isGroupLockStrict" => $user->isGroupLockStrict(),
            ];

            // really show everything should be used only for user, who is just logging/signing in
            if ($reallyShowEverything || $this->isUserLoggedInUser($user)) {
                $uiData = $user->getUiData();
                $privateData["uiData"] = $uiData ? $uiData->getData() : null;
                $privateData["settings"] = $user->getSettings();
                // ipLock is replaced with actual IP address
                $privateData["ipLock"] = $user->isIpLocked() ? $user->getIpLockRaw() : null;
                $privateData["ipLockExpiration"] = $user->isIpLocked()
                    ? $user->getIpLockExpiration()?->getTimestamp() : null;
                $privateData["groupLockExpiration"] = $user->isGroupLocked()
                    ? $user->getGroupLockExpiration()?->getTimestamp() : null;
            }
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
     * Get all information about user (bypassing ACLs) even the private ones.
     * @param User $user
     * @param bool $reallyShowEverything
     * @return array
     */
    public function getFullUser(User $user, bool $reallyShowEverything = true)
    {
        return $this->getUserData($user, true, $reallyShowEverything);
    }

    /**
     * Get as much user detail info as your permissions grants you.
     * @param User $user
     * @return array User detail
     */
    public function getUser(User $user): array
    {
        return $this->getUserData($user, $this->userAcl->canViewDetail($user));
    }

    /**
     * Get user information about given students.
     * @param User[] $users
     * @return array
     */
    public function getUsers(array $users): array
    {
        $users = array_values($users);
        return array_map(
            function (User $user) {
                return $this->getUser($user);
            },
            $users
        );
    }
}
