<?php

namespace App\Model\Repository;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\RuntimeEnvironment;

class RuntimeEnvironments extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, RuntimeEnvironment::CLASS);
  }

}
