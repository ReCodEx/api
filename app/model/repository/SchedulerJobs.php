<?php

namespace App\Model\Repository;

use App\Model\Entity\SchedulerJob;
use DateTime;
use Exception;
use Kdyby\Doctrine\EntityManager;

class SchedulerJobs extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, SchedulerJob::class);
  }

  /**
   * Find all jobs which next execution was in past, this essentially means
   * all jobs which are ready to be executed and should be executed as soon as
   * possible
   * @return SchedulerJob[]
   * @throws Exception
   */
  public function findAllReadyForExecution() {
    $now = new DateTime();
    $qb = $this->repository->createQueryBuilder("j");
    $qb->andWhere($qb->expr()->lte("j.nextExecution", ":now"))
      ->andWhere($qb->expr()->isNull("j.deletedAt"))
      ->setParameter("now", $now);

    return $qb->getQuery()->getResult();
  }
}
