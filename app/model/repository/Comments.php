<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\Criteria;
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

  public function getThread(string $id) {
    return $this->threads->find($id);
  }

  public function persistComment(Comment $comment) {
    $this->persist($comment);
  }

  public function persistThread(CommentThread $thread) {
    $this->persist($thread);
  }

  /**
   * Get the number of visible comments for given user
   * @param $threadId Id of the comments thread.
   * @param $userId Id of the viewer or null (if nobody is logged in).
   * @param bool $allVisible True = all coments visible for the user, false = comments made by the user.
   */
  public function getThreadCommentsCount(CommentThread $thread, ?User $user, bool $allVisible = true)
  {
    if (!$user) {
      return 0; // anonymous users cannot see any comments
    }

    $qb = $this->comments->createQueryBuilder('tc')
      ->select('COUNT(tc.id)')
      ->where('tc.commentThread = :id');

    if ($allVisible) {
      $qb->andWhere('tc.user = :user OR tc.isPrivate = 0');
    } else {
      $qb->andWhere('tc.user = :user');
    }

    $qb->setParameters([
      'id' => $thread->getId(),
      'user' => $user->getId(),
    ]);

    return $qb->getQuery()->getSingleScalarResult();
  }

  /**
   * Get the number of visible comments for given user
   * @param $threadId Id of the comments thread.
   * @param $userId Id of the viewer or null (if nobody is logged in).
   */
  public function getThreadLastComment(CommentThread $thread, ?User $user)
  {
    if (!$user) {
      return null; // anonymous users cannot see any comments
    }

    return $this->comments->matching(Criteria::create()
      ->where(Criteria::expr()->andX(
        Criteria::expr()->eq('commentThread', $thread),
        Criteria::expr()->orX(
          Criteria::expr()->eq('user', $user),
          Criteria::expr()->eq('isPrivate', false)
        )))
      ->orderBy(["postedAt" => Criteria::DESC])
      ->setMaxResults(1)
    )->first();
  }
}
