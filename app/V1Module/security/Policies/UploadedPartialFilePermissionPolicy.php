<?php

namespace App\Security\Policies;

use App\Model\Entity\UploadedPartialFile;
use App\Security\Identity;

class UploadedPartialFilePermissionPolicy implements IPermissionPolicy
{
    public function getAssociatedClass()
    {
        return UploadedPartialFile::class;
    }

    public function isStartedByCurrentUser(Identity $identity, UploadedPartialFile $file)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return $file->getUser() && $file->getUser()->getId() === $user->getId();
    }
}
