<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Assignment;

/**
 * @method AssignmentSolution findOrThrow($id)
 */
class AssignmentSolutions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, AssignmentSolution::class);
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
