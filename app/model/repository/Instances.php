<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Instance;

class Instances extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Instance::CLASS);
  }

  public function remove($instance, $autoFlush = TRUE) {
    foreach ($instance->licences as $licence) {
      $this->em->remove($licence);
    }
    parent::remove($instance, TRUE);
  }
}
