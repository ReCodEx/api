<?php

namespace App\Model\Repository;

use App\Model\Entity\Notification;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<Notification>
 */
class Notifications extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Notification::class);
    }

    /**
     * Find all notifications which should currently be visible, therefore current
     * datetime is in between visibleFrom and visibleTo.
     * If identifications of groups are given get only global notifications and
     * notifications for given groups.
     * @param string[] $groupsIds
     * @return Notification[]
     */
    public function findAllCurrent(array $groupsIds): array
    {
        $now = new DateTime();
        $qb = $this->repository->createQueryBuilder("n");
        $qb->andWhere($qb->expr()->lte("n.visibleFrom", ":now"))
            ->andWhere($qb->expr()->gte("n.visibleTo", ":now"))
            ->setParameter("now", $now);

        if (!empty($groupsIds)) {
            $qb->leftJoin("n.groups", "g")
                ->andWhere(
                    $qb->expr()->orX(
                        "n.groups is empty",
                        $qb->expr()->in("g.id", ":groupsIds")
                    )
                )->setParameter("groupsIds", $groupsIds);
        }

        return $qb->getQuery()->getResult();
    }
}
