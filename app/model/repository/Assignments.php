<?php

namespace App\Model\Repository;

use App\Model\Entity\Exercise;
use App\Model\Entity\User;
use App\Model\Entity\Group;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Assignment;

/**
 * @method Assignment findOrThrow($id)
 */
class Assignments extends BaseSoftDeleteRepository {
  public function __construct(EntityManager $em) {
    parent::__construct($em, Assignment::class);
  }

  public function isAssignedToUser(Exercise $exercise, User $user): bool {
    return $user->getGroups()->exists(function ($i, Group $group) use ($exercise) {
      return $this->isAssignedToGroup($exercise, $group);
    });
  }

  public function isAssignedToGroup(Exercise $exercise, Group $group): bool {
    return $group->getAssignments()->exists(function ($i, Assignment $assignment) use ($exercise) {
      return $assignment->getExercise() === $exercise;
    });
  }

  /**
   * Find assignments with deadlines within the bounds given by the parameters
   * @param DateTime $from
   * @param DateTime $to
   * @return array
   */
  public function findByDeadline(DateTime $from, DateTime $to) {
    $qb = $this->repository->createQueryBuilder("a");

    $qb->where(
      $qb->expr()->orX(
        $qb->expr()->andX(
          $qb->expr()->gt("a.firstDeadline", ":from"),
          $qb->expr()->lte("a.firstDeadline", ":to")
        ),
        $qb->expr()->andX(
          $qb->expr()->eq("a.allowSecondDeadline", ":true"),
          $qb->expr()->gt("a.secondDeadline", ":from"),
          $qb->expr()->lte("a.secondDeadline", ":to")
        )
      )
    );

    $qb->setParameters([
      "true" => TRUE,
      "from" => $from,
      "to" => $to
    ]);

    return $qb->getQuery()->getResult();
  }
}
