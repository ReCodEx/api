<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Instance;

/**
 * @method Instance findOrThrow($id)
 */
class Instances extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Instance::class);
  }
}
