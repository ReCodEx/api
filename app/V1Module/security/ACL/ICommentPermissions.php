<?php
namespace App\Security\ACL;

use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;

interface ICommentPermissions {
  function canViewThread(CommentThread $thread): bool;
  function canAlter(Comment $comment): bool;
  function canDelete(Comment $comment): bool;
  function canCreateThread(): bool;
  function canAddComment(CommentThread $thread): bool;
}