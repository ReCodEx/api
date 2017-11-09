<?php
namespace App\Model\Repository;

use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\SubmissionFailure;
use Kdyby\Doctrine\EntityManager;

class SubmissionFailures extends BaseRepository {
  public function __construct(EntityManager $em) {
    parent::__construct($em, SubmissionFailure::class);
  }

  public function findUnresolved() {
    return $this->findBy([
      "resolvedAt" => NULL
    ]);
  }

  public function findBySubmission(AssignmentSolution $submission) {
    return $this->findBy([
      "assignmentSolution" => $submission
    ]);
  }

  public function findByReferenceSolutionEvaluation(ReferenceSolutionSubmission $evaluation) {
    return $this->findBy([
      "referenceSolutionSubmission" => $evaluation
    ]);
  }
}
