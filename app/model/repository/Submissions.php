<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Submission;
use App\Model\Entity\ExerciseAssignment;

class Submissions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Submission::CLASS);
  }

  public function findSubmissions(ExerciseAssignment $assignment, string $userId) {
    return $this->submissions->findBy([
      "user" => $userId,
      "exerciseAssignment" => $assignment
    ], [
      "submittedAt" => "DESC"
    ]);
  }

}
