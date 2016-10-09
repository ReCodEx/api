<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Role;

class Roles extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Role::CLASS);
  }

  public function findLowestLevelRoles() {
    return $this->roles->findBy([ "parentRole" => NULL ]);
  }

}
