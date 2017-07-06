<?php

namespace App\Model\Repository;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Pipeline;

class Pipelines extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Pipeline::class);
  }

  /**
   * Internal simple search of pipeline names based on given string.
   * @param string|null $search
   * @return Collection
   */
  private function search(string $search = null): Collection {
    $filter = Criteria::create();

    if ($search !== null && !empty($search)) {
      $filter->where(Criteria::expr()->contains("name", $search));
    }

    return $this->matching($filter);
  }

  /**
   * Search pipelines names based on given string.
   * @param string|null $search
   * @return Pipeline[]
   */
  public function searchByName(?string $search): array {
    $foundPipelines = $this->search($search);
    if ($foundPipelines->count() > 0) {
      return $foundPipelines->toArray();
    }

    // weaker filter - the strict one did not match anything
    $foundPipelines = array();
    foreach (explode(" ", $search) as $part) {
      // skip empty parts
      $part = trim($part);
      if (empty($part)) {
        continue;
      }

      $weakPipelines = $this->search($part);
      $foundPipelines = array_merge($foundPipelines, $weakPipelines->toArray());
    }

    return $foundPipelines;
  }

}
