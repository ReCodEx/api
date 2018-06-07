<?php

namespace App\Model\Repository;

use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\ShadowAssignmentEvaluation;
use App\Model\Entity\User;
use Kdyby\Doctrine\EntityManager;

/**
 * @method ShadowAssignmentEvaluation findOrThrow($id)
 */
class ShadowAssignmentEvaluations extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ShadowAssignmentEvaluation::class);
  }

  /**
   * Find best solutions of given assignments for user.
   * @param ShadowAssignment[] $shadowAssignments
   * @param User $user
   * @return ShadowAssignmentEvaluation[]
   */
  public function findEvaluationsForAssignment(array $shadowAssignments, User $user): array {
    return $this->findBy([
      "author" => $user,
      "assignment" => $shadowAssignments,
    ]);
  }

}
