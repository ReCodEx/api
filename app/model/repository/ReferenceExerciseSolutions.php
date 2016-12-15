<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\ReferenceExerciseSolution;

class ReferenceExerciseSolutions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ReferenceExerciseSolution::class);
  }

}
