<?php

namespace App\Security\ACL;

use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;

interface ICommentPermissions
{
    public function canViewThread(CommentThread $thread): bool;

    public function canAlter(Comment $comment): bool;

    public function canDelete(Comment $comment): bool;

    public function canCreateThread(): bool;

    public function canAddComment(CommentThread $thread): bool;
}
