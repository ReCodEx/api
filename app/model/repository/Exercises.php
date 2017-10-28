<?php

namespace App\Model\Repository;

use App\Model\Entity\LocalizedExercise;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
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
    if ($search === NULL) {
      return $this->findAll();
    }

    return $this->searchHelper($search, function ($search) {
      $idsQueryBuilder = $this->em->createQueryBuilder()->addSelect("l.id")->from(LocalizedExercise::class, "l");
      $idsQueryBuilder->where($idsQueryBuilder->expr()->like("l.name", ":search"));
      $idsQueryBuilder->setParameter("search", "%" . $search . "%");
      $textIds = array_column($idsQueryBuilder->getQuery()->getScalarResult(), "id");

      $exercisesQueryBuilder = $this->em->createQueryBuilder()->addSelect("e")->from(Exercise::class, "e");

      foreach ($textIds as $i => $textId) {
        $exercisesQueryBuilder->orWhere($exercisesQueryBuilder->expr()->isMemberOf("?" . $i, "e.localizedTexts"));
        $exercisesQueryBuilder->setParameter($i, $textId);
      }

      return $exercisesQueryBuilder->getQuery()->getResult();
    });
  }
}
