<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

class Groups extends Nette\Object {
  protected $entityName = "Group";

  public function persist(Group $group) {
    $this->em->persist($group);
  }

  public function findAllByInstance(Instance $instance) {
    
  }
}
