<?php

namespace App\Model\Repository;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Pipeline;

/**
 * @method Pipeline findOrThrow($id)
 * @method Pipeline get($id)
 */
class Pipelines extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Pipeline::class);
  }


  /**
   * Search pipelines names based on given string.
   * @param string|null $search
   * @return Pipeline[]
   */
  public function searchByName(?string $search): array {
    return $this->searchBy(["name"], $search);
  }

}
