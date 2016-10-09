<?php

namespace App\Model\Repository;

use App\Model\Entity\Resource;
use Kdyby\Doctrine\EntityManager;

class Resources extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Resource::CLASS);
  }

}
