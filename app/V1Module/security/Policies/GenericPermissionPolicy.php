<?php

namespace App\Security\Policies;

use App\Security\Identity;

/**
 * A policy that is not bound to a particular entity.
 * It gathers generic checks performed solely on the entity of the logged-in user.
 */
class GenericPermissionPolicy implements IPermissionPolicy
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
