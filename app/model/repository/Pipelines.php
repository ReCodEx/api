<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Pipeline;

class Pipelines extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Pipeline::class);
  }

}
