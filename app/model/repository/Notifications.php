<?php

namespace App\Model\Repository;

use App\Model\Entity\Notification;
use DateTime;
use Kdyby\Doctrine\EntityManager;


class Notifications extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Notification::class);
  }

  /**
   * Find all notifications which should currently be visible, therefore current
   * datetime is in between visibleFrom and visibleTo.
   * @return Notification[]
   */
  public function findAllCurrent(): array {
    $now = new DateTime();
    $qb = $this->repository->createQueryBuilder();
    $qb->andWhere($qb->expr()->lte("visibleFrom", ":now"))
      ->andWhere($qb->expr()->gte("visibleTo", ":now"))
      ->setParameter("now", $now);
    return $qb->getQuery()->getResult();
  }
}
