<?php

namespace App\Security\Policies;

use App\Model\Entity\CommentThread;
use App\Security\Identity;

class CommentThreadPermissionPolicy implements IPermissionPolicy
{
    public function getAssociatedClass()
    {
        return CommentThread::class;
    }
}
