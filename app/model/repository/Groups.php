<?php

namespace App\Model\Repository;

class Groups extends BaseRepository {
  protected $entityName = "Group";

  public function findAllByInstance(Instance $instance) {
    $this->repository->findBy([ 'instance' => $instance->getId() ]);
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
