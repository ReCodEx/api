<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

class Groups extends Nette\Object {
  private $em;
  private $groups;

  public function __construct(EntityManager $em) {
    $this->em = $em;
    $this->groups = $em->getRepository("App\Model\Entity\Group");
  }

  public function findAll() {
    return $this->groups->findAll();
  }

  public function get($id) {
    return $this->groups->findOneById($id);
  }

  public function persist(Group $group) {
    $this->em->persist($group);
  }
}
