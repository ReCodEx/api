<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\Criteria;
use App\Model\Entity\Exercise;
use App\Model\Entity\User;

class Exercises extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Exercise::class);
  }

  /**
   * Replace all localizations in exercise with given ones.
   * @param Exercise $exercise
   * @param array $localizations localizations which will be placed to exercise
   * @param bool $flush if true then all changes will be flush at the end
   */
  public function replaceLocalizedTexts(Exercise $exercise, array $localizations, bool $flush = TRUE) {
    $originalLocalizations = $exercise->getLocalizedTexts()->toArray();

    foreach ($localizations as $localized) {
      $exercise->addLocalizedText($localized);
      $this->persist($localized);
    }

    foreach ($originalLocalizations as $localization) {
      $exercise->removeLocalizedText($localization);
    }

    if ($flush) {
      $this->flush();
    }
  }

  /**
   * Replace all runtime configurations in exercise with given ones.
   * @param Exercise $exercise
   * @param array $configs configurations which will be placed to exercise
   * @param bool $flush if true then all changes will be flush at the end
   */
  public function replaceRuntimeConfigs(Exercise $exercise, array $configs, bool $flush = TRUE) {
    $originalConfigs = $exercise->getRuntimeConfigs()->toArray();

    foreach ($configs as $config) {
      $exercise->addRuntimeConfig($config);
      $this->persist($config);
    }

    foreach ($originalConfigs as $config) {
      $exercise->removeRuntimeConfig($config);
    }

    if ($flush) {
      $this->flush();
    }
  }

  /**
   *
   * @param string|NULL $search
   * @return array
   */
  public function searchByName($search, User $user) {
    // @todo: this maybe has to be somehow rewritten according to group exercises

    if ($search !== NULL && !empty($search)) {
      $filter = Criteria::create()->where(Criteria::expr()->contains("name", $search));
      if ($user->getRole()->hasLimitedRights()) {
        $filter->andWhere(Criteria::expr()->orX(
          Criteria::expr()->eq("isPublic", TRUE),
          Criteria::expr()->eq("author", $user)
        ));
      }

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

        $filter = Criteria::create()->where(Criteria::expr()->contains("name", $part));
        if ($user->getRole()->hasLimitedRights()) {
          $filter->andWhere(Criteria::expr()->orX(
            Criteria::expr()->eq("isPublic", TRUE),
            Criteria::expr()->eq("author", $user)
          ));
        }

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
