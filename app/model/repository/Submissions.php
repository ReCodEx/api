<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Submission;
use App\Model\Entity\Assignment;

class Submissions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Submission::class);
  }

  public function findSubmissions(Assignment $assignment, string $userId) {
    return $this->findBy([
      "user" => $userId,
      "assignment" => $assignment
    ], [
      "submittedAt" => "DESC"
    ]);
  }

  public function findPublicSubmissions($assignment, $userId)
  {
    return $this->findBy([
      "user" => $userId,
      "assignment" => $assignment,
      "private" => FALSE
    ], [
      "submittedAt" => "DESC"
    ]);
  }

}
