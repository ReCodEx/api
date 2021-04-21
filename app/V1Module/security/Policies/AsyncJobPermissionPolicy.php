<?php

namespace App\Security\Policies;

use App\Model\Entity\AsyncJob;
use App\Security\Identity;

class AsyncJobPermissionPolicy implements IPermissionPolicy
{
    public function getAssociatedClass()
    {
        return AsyncJob::class;
    }

    public function isCreator(Identity $identity, AsyncJob $job)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return $user === $job->getCreatedBy();
    }
}
