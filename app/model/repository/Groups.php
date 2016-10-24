<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Group;

class Groups extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Group::CLASS);
  }

  public function findAllByInstance(Instance $instance) {
    return $this->repository->findBy([ 'instance' => $instance->getId() ]);
  }

  public function nameIsFree($name, $instanceId, $parentGroupId = NULL) {
    $name = trim($name);
    $groups = $this->repository->findBy([
      "name" => $name,
      "parentGroup" => $parentGroupId,
      "instance" => $instanceId
    ]);

    return count($groups) === 0;
  }
}
