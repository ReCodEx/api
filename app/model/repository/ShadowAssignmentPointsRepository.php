<?php

namespace App\Model\Repository;

use App\Helpers\Pair;
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
   * @return Pair[] indexed by the shadow assignment identification, value is
   * pair which key is assignment and value is points. Points can be null if
   * there are no points for the assignment and user
   */
  public function findPointsForAssignments(array $shadowAssignments, User $user): array {
    $result = [];
    foreach ($shadowAssignments as $assignment) {
      $result[$assignment->getId()] = new Pair($assignment, null);
    }

    $pointsList = $this->findBy([
      "author" => $user,
      "shadowAssignment" => $shadowAssignments, // doctrine will handle given array with IN operator
    ]);

    /** @var ShadowAssignmentPoints $points */
    foreach ($pointsList as $points) {
      $result[$points->getShadowAssignment()->getId()]->value = $points;
    }
    return $result;
  }

}
