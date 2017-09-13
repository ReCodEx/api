<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use App\Model\Entity\Exercise;
use App\Model\Entity\User;

/**
 * @method Exercise findOrThrow($exerciseId)
 */
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
  public function replaceEnvironmentConfigs(Exercise $exercise, array $configs, bool $flush = TRUE) {
    $originalConfigs = $exercise->getExerciseEnvironmentConfigs()->toArray();
    foreach ($configs as $config) {
      $exercise->addExerciseEnvironmentConfig($config);
    }
    foreach ($originalConfigs as $config) {
      $exercise->removeExerciseEnvironmentConfig($config);
    }
    if ($flush) {
      $this->flush();
    }
  }

  /**
   * Internal simple search of exercises names based on given string.
   * @param string|NULL $search
   * @return Collection
   */
  private function search(?string $search = NULL): Collection {
    $filter = Criteria::create();

    if ($search !== NULL && !empty($search)) {
      $filter->where(Criteria::expr()->contains("name", $search));
    }

    return $this->matching($filter);
  }

  /**
   * Search exercises names based on given string.
   * @param string|NULL $search
   * @return Exercise[]
   */
  public function searchByName(?string $search): array {
    $foundExercises = $this->search($search);
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

      $weakExercises = $this->search($part);
      $foundExercises = array_merge($foundExercises, $weakExercises->toArray());
    }

    return $foundExercises;
  }

}
