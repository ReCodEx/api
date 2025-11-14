<?php

namespace App\Security\Policies;

use App\Model\Repository\ExternalLogins;
use App\Security\ACL\SisIdWrapper;
use App\Security\Identity;

/**
 * @deprecated Use the new SIS extension instead
 */
class SisPermissionPolicy implements IPermissionPolicy
{
    private $externalLogins;

    public function getAssociatedClass()
    {
        return SisIdWrapper::class;
    }

    public function __construct(ExternalLogins $externalLogins)
    {
        $this->externalLogins = $externalLogins;
    }

    public function isLinkedToUser(Identity $identity, SisIdWrapper $id)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        $login = $this->externalLogins->findOneBy(
            [
                'user' => $user,
                'authService' => 'cas-uk',
                'externalId' => $id->get()
            ]
        );

        return $login !== null;
    }
}
