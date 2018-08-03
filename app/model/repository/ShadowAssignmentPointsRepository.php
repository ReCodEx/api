<?php

namespace App\Model\Repository;

use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\ShadowAssignmentPoints;
use App\Model\Entity\User;
use Kdyby\Doctrine\EntityManager;

/**
 * @method ShadowAssignmentPoints findOrThrow($id)
 */
class ShadowAssignmentPointsRepository extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ShadowAssignmentPoints::class);
  }

  /**
   * Find best solutions of given assignments for user.
   * @param ShadowAssignment[] $shadowAssignments
   * @param User $user
   * @return ShadowAssignmentPoints[]
   */
  public function findEvaluationsForAssignment(array $shadowAssignments, User $user): array {
    return $this->findBy([
      "author" => $user,
      "shadowAssignment" => $shadowAssignments,
    ]);
  }

}
