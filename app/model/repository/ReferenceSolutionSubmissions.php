<?php

namespace App\Model\Repository;

use App\Model\Entity\ReferenceSolutionSubmission;
use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use DateTime;

class ReferenceSolutionSubmissions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ReferenceSolutionSubmission::class);
  }

  /**
   * Find all submissions created in given time interval.
   * @param DateTime|null $since Only submissions created after this date are returned.
   * @param DateTime|null $until Only submissions created before this date are returned.
   */
  public function findByCreatedAt(?DateTime $since, ?DateTime $until)
  {
    $qb = $this->createQueryBuilder('s'); // takes care of softdelete cases
    if ($since) {
      $qb->andWhere('s.submittedAt >= :since')->setParameter('since', $since);
    }
    if ($until) {
      $qb->andWhere('s.submittedAt <= :until')->setParameter('until', $until);
    }
    return $qb->getQuery()->getResult();
  }
}
