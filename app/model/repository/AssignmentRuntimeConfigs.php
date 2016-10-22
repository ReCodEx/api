<?php

namespace App\Model\Repository;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\AssignmentRuntimeConfig;

class AssignmentRuntimeConfigs extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, AssignmentRuntimeConfig::CLASS);
  }

}
