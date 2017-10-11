<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\ReferenceExerciseSolution;

/**
 * @method ReferenceExerciseSolution findOrThrow($solutionId)
 */
class ReferenceExerciseSolutions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ReferenceExerciseSolution::class);
  }

}
