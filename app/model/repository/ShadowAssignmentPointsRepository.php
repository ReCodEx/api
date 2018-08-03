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
   * Find points for given user of given assignments.
   * @param ShadowAssignment[] $shadowAssignments
   * @param User $user
   * @return ShadowAssignmentPoints[]
   */
  public function findPointsForAssignments(array $shadowAssignments, User $user): array {
    return $this->findBy([
      "author" => $user,
      "shadowAssignment" => $shadowAssignments, // doctrine will handle given array with IN operator
    ]);
  }

}
