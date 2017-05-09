<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use App\Model\Entity\Exercise;
use App\Model\Entity\User;
use App\Model\Entity\Group;

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
   * Search exercises names based on given string.
   * @param User $user
   * @param string|NULL $search
   * @return Collection
   */
  private function search(User $user, $search = NULL) {
    $filter = Criteria::create();

    // @todo: Criteria::expr()->in($user->getGroups()->map( <get id> ))

    if ($search !== NULL && !empty($search)) {
      $filter->where(Criteria::expr()->contains("name", $search));
    }

    if ($user->getRole()->hasLimitedRights()) {
      $filter->andWhere(Criteria::expr()->orX(
        Criteria::expr()->andX(Criteria::expr()->eq("isPublic", TRUE),
          Criteria::expr()->eq("group", NULL)),
        Criteria::expr()->andX(Criteria::expr()->eq("isPublic", TRUE),
          Criteria::expr()->in("group", $user->getGroupsAsSupervisor()->map(
            function (Group $group) {
              return $group->getId();
          })->getValues())),
        Criteria::expr()->eq("author", $user)
      ));
    }

    return $this->matching($filter);
  }

  /**
   *
   * @param string|NULL $search
   * @param User $user
   * @return array
   */
  public function searchByName($search, User $user) {
    $foundExercises = $this->search($user, $search);
    if ($foundExercises->count() > 0) {
      return $foundExercises->toArray();
    }

    // weaker filter - the strict one did not match anything
    $foundExercises = array();
    foreach (explode(" ", $search) as $part) {
      // skip empty parts
      $part = trim($part);
      if (empty($part)) {
        continue;
      }

      $weakExercises = $this->search($user, $part);
      $foundExercises = array_merge($foundExercises, $weakExercises->toArray());
    }

    return $foundExercises;
  }


}
