<?php
namespace App\Model\Repository;

use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Model\Entity\Submission;
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

  public function findBySubmission(Submission $submission) {
    return $this->findBy([
      "submission" => $submission
    ]);
  }

  public function findByReferenceSolutionEvaluation(ReferenceSolutionEvaluation $evaluation) {
    return $this->findBy([
      "referenceSolutionEvaluation" => $evaluation
    ]);
  }
}