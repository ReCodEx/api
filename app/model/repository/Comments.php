<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;

class Comments extends Nette\Object {

  private $em;
  private $comments;
  private $threads;

  public function __construct(EntityManager $em) {
    $this->em = $em;
    $this->comments = $em->getRepository('App\Model\Entity\Comment');
    $this->threads = $em->getRepository('App\Model\Entity\CommentThread');
  }

  public function get($id) {
    return $this->threads->findOneById($id);
  }

  public function persistComment(Comment $comment) {
    $this->em->persist($comment);
  }

  public function persistThread(CommentThread $thread) {
    $this->em->persist($thread);
  }

  public function flush() {
    $this->em->flush();
  }
}
