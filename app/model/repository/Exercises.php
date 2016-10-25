<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use App\Model\Entity\Exercise;

class Exercises extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Exercise::CLASS);
  }

  public function searchByNameOrId(string $search) {
    if ($search !== NULL && !empty($search)) {
      $filter = Criteria::create()
                  ->where(Criteria::expr()->contains("id", $search))
                  ->orWhere(Criteria::expr()->contains("name", $search));
      $foundExercises = $this->matching($filter);
      if ($foundExercises->count() > 0) {
        return $foundExercises->toArray();
      }

      // weaker filter - the strict one did not match anything
      foreach (explode(" ", $search) as $part) {
        // skip empty parts
        $part = trim($part);
        if (empty($part)) {
          continue;
        }

        $filter = Criteria::create()
                    ->orWhere(Criteria::expr()->contains("id", $part))
                    ->orWhere(Criteria::expr()->contains("name", $part));
        $foundExercises = $this->matching($filter);
      }

      return $foundExercises->toArray();
    } else {
      // no query is present
      return $this->findAll();
    }
  }


}
