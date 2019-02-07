<?php

namespace App\Model\Repository;

use App\Model\Entity\ExerciseTag;
use Kdyby\Doctrine\EntityManager;

class ExerciseTags extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ExerciseTag::class);
  }

}
