<?php

namespace App\Model\Repository;

use App\Model\Entity\GroupMembership;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<GroupMembership>
 */
class GroupMemberships extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, GroupMembership::class);
    }

    /**
     * Find all group memberships for a specific user in non-archived groups.
     * @param string $userId
     * @return GroupMembership[]
     */
    public function findByUser(string $userId): array
    {
        $qb = $this->createQueryBuilder('gm')->join('gm.group', 'g');
        $qb->where('g.archivedAt IS NULL');
        $qb->andWhere($qb->expr()->eq('gm.user', ':userId'))
            ->setParameter('userId', $userId);
        return $qb->getQuery()->getResult();
    }
}
