<?php

namespace App\Security\Policies;

use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Security\Identity;
use App\Security\Roles;

/**
 * Base policy is not bound to particular entity.
 * It gathers generic checks performed solely on the entity of the logged-in user.
 */
class BasePermissionPolicy implements IPermissionPolicy
{
    public function getAssociatedClass()
    {
        return '';
    }

    public function userIsNotGroupLocked(Identity $identity): bool
    {
        $user = $identity->getUserData();
        return $user && !$user->isGroupLocked();
    }
}
