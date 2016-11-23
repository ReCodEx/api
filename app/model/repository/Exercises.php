<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\Criteria;
use App\Model\Entity\Exercise;
use App\Model\Entity\User;

class Exercises extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Exercise::CLASS);
  }

  /**
   *
   * @param string|NULL $search
   * @return array
   */
  public function searchByName($search, User $user) {
    // superadmin can view all exercises
    if (!$user->getRole()->hasLimitedRights()) {
      return $this->findAll();
    }

    if ($search !== NULL && !empty($search)) {
      $filter = Criteria::create()
                  ->where(Criteria::expr()->contains("name", $search))
                  ->andWhere(Criteria::expr()->orX(
                      Criteria::expr()->eq("isPublic", TRUE),
                      Criteria::expr()->eq("author", $user)
                  ));
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
                    ->where(Criteria::expr()->contains("name", $part))
                    ->andWhere(Criteria::expr()->orX(
                        Criteria::expr()->eq("isPublic", TRUE),
                        Criteria::expr()->eq("author", $user)
                    ));
        $foundExercises = $this->matching($filter);
      }

      return $foundExercises->toArray();
    } else {
      // no query is present
      $filter = Criteria::create()
        ->where(Criteria::expr()->eq("isPublic", TRUE))
        ->orWhere(Criteria::expr()->eq("author", $user));
      return $this->matching($filter)->toArray();
    }
  }


}
