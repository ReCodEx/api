<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;
use App\Model\Entity\User;

class Comments extends BaseRepository {

  private $threads;

  public function __construct(EntityManager $em) {
    parent::__construct($em, Comment::CLASS);
    $this->threads = $em->getRepository(CommentThread::CLASS);
  }

  public function get($id) {
    return $this->threads->findOneById($id);
  }

  public function persistComment(Comment $comment) {
    $this->persist($comment);
  }

  public function persistThread(CommentThread $thread) {
    $this->em->persist($thread);
  }

  public function findUsersComment(User $user, string $id) {
    return $this->comments->findOneBy([ "user" => $user, "id" => $id ]);
  }

}
