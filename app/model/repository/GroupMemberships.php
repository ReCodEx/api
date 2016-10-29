<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\GroupMembership;

class GroupMemberships extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, GroupMembership::CLASS);
  }

}
