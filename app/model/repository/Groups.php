<?php

namespace App\Model\Repository;

use App\Model\Entity\Instance;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Group;

/**
 * @method Group findOrThrow($id)
 */
class Groups extends BaseSoftDeleteRepository  {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Group::class);
  }

  /**
   * Find all groups belonging to specified instance.
   * @param Instance $instance
   * @return array
   */
  public function findAllByInstance(Instance $instance) {
    return $this->findBy([ 'instance' => $instance->getId() ]);
  }

  /**
   * Check if the name of the group is free within group and instance.
   * @param $name
   * @param $instanceId
   * @param null $parentGroupId
   * @return bool
   */
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
