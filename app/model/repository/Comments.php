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
  private $comments;

  public function __construct(EntityManager $em) {
    parent::__construct($em, Comment::class);
    $this->threads = $em->getRepository(CommentThread::class);
    $this->comments = $em->getRepository(Comment::class);
  }

  public function get($id) {
    return $this->threads->findOneById($id);
  }

  public function persistComment(Comment $comment) {
    $this->persist($comment);
  }

  public function persistThread(CommentThread $thread) {
    $this->persist($thread);
  }
}
