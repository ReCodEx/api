<?php

namespace App\Model\Repository;

use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTag;
use Kdyby\Doctrine\EntityManager;

class ExerciseTags extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ExerciseTag::class);
  }

  public function findByNameAndExercise(string $name, Exercise $exercise): ?ExerciseTag {
    return $this->findOneBy([
      "name" => $name,
      "exercise" => $exercise
    ]);
  }

  /**
   * Get all exercise tag names distinct by their names.
   * @return string[]
   */
  public function findAllDistinctNames(): array {
    $qb = $this->repository->createQueryBuilder("e")->select("e.name")->distinct();
    $result = $qb->getQuery()->getResult();
    return array_column($result, "name");
  }
}
