<?php

namespace App\Model\Repository;

use App\Model\Entity\SchedulerJob;
use Kdyby\Doctrine\EntityManager;

class SchedulerJobs extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, SchedulerJob::class);
  }
}
