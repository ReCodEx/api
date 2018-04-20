<?php

namespace App\Model\Repository;

use App\Model\Entity\LocalizedExercise;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Exercise;

/**
 * @method Exercise findOrThrow($id)
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
  public function replaceEnvironmentConfigs(Exercise $exercise, array $configs, bool $flush = true) {
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
   * @param string|null $search
   * @return Exercise[]
   */
  public function searchByName(?string $search): array {
    if ($search === null || empty($search)) {
      return $this->findAll();
    }

    return $this->searchHelper($search, function ($search) {
      $idsQueryBuilder = $this->em->createQueryBuilder()->addSelect("l.id")->from(LocalizedExercise::class, "l");
      $idsQueryBuilder->where($idsQueryBuilder->expr()->like("l.name", ":search"));
      $idsQueryBuilder->setParameter("search", "%" . $search . "%");
      $textIds = array_column($idsQueryBuilder->getQuery()->getScalarResult(), "id");

      $exercisesQueryBuilder = $this->em->createQueryBuilder()->addSelect("e")->from(Exercise::class, "e");
      $exercisesQueryBuilder->where($exercisesQueryBuilder->expr()->isNull("e.deletedAt"));

      $criteria = [];
      foreach ($textIds as $i => $textId) {
        $criteria[] = $exercisesQueryBuilder->expr()->isMemberOf("?" . $i, "e.localizedTexts");
        $exercisesQueryBuilder->setParameter($i, $textId);
      }
      $exercisesQueryBuilder->andWhere($exercisesQueryBuilder->expr()->orX(...$criteria));

      return $exercisesQueryBuilder->getQuery()->getResult();
    });
  }
}
