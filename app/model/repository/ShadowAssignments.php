<?php

namespace App\Model\Repository;

use App\Model\Entity\ShadowAssignment;
use Kdyby\Doctrine\EntityManager;

/**
 * @method ShadowAssignment findOrThrow($id)
 */
class ShadowAssignments extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ShadowAssignment::class);
  }

}
