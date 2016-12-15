<?php

namespace App\Model\Repository;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Licence;

class Licences extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Licence::class);
  }

}
