<?php
namespace App\Model\Repository;

use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\SubmissionFailure;
use Kdyby\Doctrine\EntityManager;


/**
 * @method SubmissionFailure findOrThrow($id)
 */
class SubmissionFailures extends BaseRepository {
  public function __construct(EntityManager $em) {
    parent::__construct($em, SubmissionFailure::class);
  }

  public function findUnresolved() {
    return $this->findBy([
      "resolvedAt" => null
    ]);
  }
}
