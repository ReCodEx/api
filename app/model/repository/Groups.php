<?php

namespace App\Model\Repository;

use App\Model\Entity\Instance;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Group;

class Groups extends BaseSoftDeleteRepository  {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Group::class);
  }

  public function findAllByInstance(Instance $instance) {
    return $this->findBy([ 'instance' => $instance->getId() ]);
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
