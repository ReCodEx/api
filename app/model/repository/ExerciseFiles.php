<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\ExerciseFile;

class ExerciseFiles extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ExerciseFile::CLASS);
  }

}
