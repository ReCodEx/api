<?php

namespace App\Model\Repository;

use App\Model\Entity\ShadowAssignment;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;

/**
 * @extends BaseSoftDeleteRepository<ShadowAssignment>
 */
class ShadowAssignments extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ShadowAssignment::class);
    }

    /**
     * Find shadow assignments with deadlines within the bounds given by the parameters
     * @param DateTime $from
     * @param DateTime $to
     * @return ShadowAssignment[]
     */
    public function findByDeadline(DateTime $from, DateTime $to): array
    {
        $qb = $this->createQueryBuilder("a");
        $qb->andWhere(
            $qb->expr()->andX(
                $qb->expr()->isNotNull("a.deadline"),
                $qb->expr()->gt("a.deadline", ":from"),
                $qb->expr()->lte("a.deadline", ":to")
            )
        );

        $qb->setParameters([ "from" => $from, "to" => $to ]);
        return $qb->getQuery()->getResult();
    }
}
