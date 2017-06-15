<?php
namespace App\Security\Policies;

use App\Model\Entity\Comment;
use App\Security\Identity;

class CommentPermissionPolicy implements IPermissionPolicy {
  function getAssociatedClass() {
    return Comment::class;
  }

  public function isAuthor(Identity $identity, Comment $comment) {
    $user = $identity->getUserData();
    if (!$user) {
      return FALSE;
    }

    return $user === $comment->getUser();
  }
}