<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Permission;

class Permissions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Permission::class);
  }

}
