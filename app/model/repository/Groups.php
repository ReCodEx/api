<?php

namespace App\Model\Repository;

class Groups extends BaseRepository {
  protected $entityName = "Group";

  public function findAllByInstance(Instance $instance) {
    $this->repository->findBy([ 'instance' => $instance->getId() ]);
  }
}
