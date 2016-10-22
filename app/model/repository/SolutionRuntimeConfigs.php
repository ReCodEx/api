<?php

namespace App\Model\Repository;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\SolutionRuntimeConfig;

class SolutionRuntimeConfigs extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, SolutionRuntimeConfig::CLASS);
  }

}
