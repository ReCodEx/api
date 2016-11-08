<?php

namespace App\Model\Repository;

use App\Model\Entity\HardwareGroup;
use Kdyby\Doctrine\EntityManager;

class HardwareGroups extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, HardwareGroup::CLASS);
  }

}
