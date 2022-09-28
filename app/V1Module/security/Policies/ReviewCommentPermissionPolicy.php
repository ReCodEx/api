<?php

namespace App\Security\Policies;

use App\Model\Entity\ReviewComment;
use App\Security\Identity;

class ReviewCommentPermissionPolicy implements IPermissionPolicy
{
    public function getAssociatedClass()
    {
        return ReviewComment::class;
    }

    public function isAuthor(Identity $identity, ReviewComment $reviewComment)
    {
        $user = $identity->getUserData();
        $author = $reviewComment->getAuthor();
        return $user && $author && $user->getId() === $author->getId();
    }
}
