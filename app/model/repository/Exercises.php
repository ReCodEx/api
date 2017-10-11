<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Exercise;

/**
 * @method Exercise findOrThrow($solutionId)
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
   * Search exercises names based on given string.
   * @param string|NULL $search
   * @return Exercise[]
   */
  public function searchByName(?string $search): array {
    return $this->searchBy(["name"], $search);
  }

}
