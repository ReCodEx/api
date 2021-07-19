<?php

namespace App\Model\Repository;

use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\ShadowAssignmentPoints;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ShadowAssignmentPoints>
 */
class ShadowAssignmentPointsRepository extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ShadowAssignmentPoints::class);
    }

    /**
     * Find points for given user of given assignments.
     * @param ShadowAssignment[] $shadowAssignments
     * @param User $awardee
     * @return ShadowAssignmentPoints[] indexed by the shadow assignment
     * identification, value is shadow assignment points for the assignment
     */
    public function findPointsForAssignments(array $shadowAssignments, User $awardee): array
    {
        $pointsList = $this->findBy(
            [
                "awardee" => $awardee,
                "shadowAssignment" => $shadowAssignments, // doctrine will handle given array with IN operator
            ]
        );

        $result = [];
        foreach ($pointsList as $points) {
            /** @var ShadowAssignmentPoints $points */
            if ($points->getShadowAssignment() === null) {
                continue;
            }
            $result[$points->getShadowAssignment()->getId()] = $points;
        }
        return $result;
    }
}
