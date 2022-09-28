<?php

namespace App\Model\Repository;

use App\Model\Entity\ReviewComment;
use App\Model\Entity\AssignmentSolution;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ReviewComment>
 */
class ReviewComments extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ReviewComment::class);
    }

    /**
     * Remove all review comments of selected solution
     * @param AssignmentSolution $solution
     * @return int number of comments removed
     */
    public function deleteCommentsOfSolution(AssignmentSolution $solution): int
    {
        $qb = $this->createQueryBuilder('rc');
        $qb->delete(ReviewComment::class, 'rc')
            ->where('rc.solution = :solution')
            ->setParameter('solution', $solution->getId());
        return $qb->getQuery()->execute();
    }
}
