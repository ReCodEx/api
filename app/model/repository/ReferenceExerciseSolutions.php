<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\ReferenceExerciseSolution;

class ReferenceExerciseSolutions extends Nette\Object {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ReferenceExerciseSolution::CLASS);
  }

}
