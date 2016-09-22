<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\ExerciseAssignment;

class ExerciseAssignments extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ExerciseAssignment::CLASS);
  }

}
