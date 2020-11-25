<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\Criteria;
use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;
use App\Model\Entity\User;

class Comments extends BaseRepository
{

    private $threads;
    private $comments;

    public function __construct(EntityManager $em)
    {
        parent::__construct($em, Comment::class);
        $this->threads = $em->getRepository(CommentThread::class);
        $this->comments = $em->getRepository(Comment::class);
    }

    public function getThread(string $id): ?CommentThread
    {
        return $this->threads->find($id);
    }

    /**
     * Get the number of visible comments for given user.
     * The method requires the user entity, since it does not use ACL mechanism and compares
     * the authorship of comments directly as a performance optimization (we need only the count, not all entities).
     * @param CommentThread $thread
     * @param User $user
     * @param bool $allVisible True = all coments visible for the user, false = comments made by the user.
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function getCommentsCount(CommentThread $thread, User $user, bool $allVisible)
    {
        $qb = $this->comments->createQueryBuilder('tc')
            ->select('COUNT(tc.id)')
            ->where('tc.commentThread = :id');

        if ($allVisible) {
            $qb->andWhere('tc.user = :user OR tc.isPrivate = 0');
        } else {
            $qb->andWhere('tc.user = :user');
        }

        $qb->setParameters(
            [
                'id' => $thread->getId(),
                'user' => $user->getId(),
            ]
        );

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get the number of all visible comments for given user.
     * @param CommentThread $thread
     * @param User $user
     * @return int
     */
    public function getThreadCommentsCount(CommentThread $thread, User $user)
    {
        return $this->getCommentsCount($thread, $user, true);
    }

    /**
     * Get the number of comments made directly by given user.
     * @param CommentThread $thread
     * @param User $user
     * @return int
     */
    public function getAuthoredCommentsCount(CommentThread $thread, User $user)
    {
        return $this->getCommentsCount($thread, $user, false);
    }

    /**
     * Get the number of visible comments for given user
     * The method requires the user entity, since it does not use ACL mechanism and compares
     * the authorship of comments directly as a performance optimization (we need only the last entity, not all entities).
     * @param CommentThread $thread
     * @param User $user
     * @return mixed
     */
    public function getThreadLastComment(CommentThread $thread, User $user)
    {
        return $this->comments->matching(
            Criteria::create()
                ->where(
                    Criteria::expr()->andX(
                        Criteria::expr()->eq('commentThread', $thread),
                        Criteria::expr()->orX(
                            Criteria::expr()->eq('user', $user),
                            Criteria::expr()->eq('isPrivate', false)
                        )
                    )
                )
                ->orderBy(["postedAt" => Criteria::DESC])
                ->setMaxResults(1)
        )->first();
    }
}
