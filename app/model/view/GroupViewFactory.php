<?php

namespace App\Model\View;

use App\Helpers\EvaluationStatus\EvaluationStatus;
use App\Model\Entity\Assignment;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Model\Repository\AssignmentSolutions;
use Doctrine\Common\Collections\Collection;


/**
 * Factory for group views which somehow do not fit into json serialization of
 * entities.
 */
class GroupViewFactory {

  /**
   * @var AssignmentSolutions
   */
  private $assignmentSolutions;

  public function __construct(AssignmentSolutions $assignmentSolutions) {
    $this->assignmentSolutions = $assignmentSolutions;
  }


  /**
   * Get collection of completed assignments by given user.
   * @param Group $group
   * @param User $student
   * @return Collection
   */
  private function getCompletedAssignmentsByStudent(Group $group, User $student) {
    return $group->getAssignments()->filter(
      function(Assignment $assignment) use ($student) {
        return $this->assignmentSolutions->findBestSolution($assignment, $student) !== null;
      }
    );
  }

  /**
   * Get collection of assignments which given user do not submitted yet.
   * @param Group $group
   * @param User $student
   * @return Collection
   */
  private function getMissedAssignmentsByStudent(Group $group, User $student) {
    return $group->getAssignments()->filter(
      function(Assignment $assignment) use ($student) {
        $best = $this->assignmentSolutions->findBestSolution($assignment, $student);
        return $assignment->isAfterDeadline() && $best === null;
      }
    );
  }

  /**
   * Get total sum of points which given user gained in group.
   * @param Group $group
   * @param User $student
   * @return int
   */
  private function getPointsGainedByStudent(Group $group, User $student) {
    return array_reduce(
      $this->getCompletedAssignmentsByStudent($group, $student)->getValues(),
      function ($carry, Assignment $assignment) use ($student) {
        $best = $this->assignmentSolutions->findBestSolution($assignment, $student);
        if ($best !== NULL) {
          $carry += $best->getTotalPoints();
        }

        return $carry;
      },
      0
    );
  }

  /**
   * Get the statistics of an individual student.
   * @param Group $group
   * @param User $student Student of this group
   * @return array Students statistics
   */
  public function getStudentsStats(Group $group, User $student) {
    $total = $group->getAssignments()->count();
    $completed = $this->getCompletedAssignmentsByStudent($group, $student);
    $missed = $this->getMissedAssignmentsByStudent($group, $student);
    $maxPoints = $group->getMaxPoints();
    $gainedPoints = $this->getPointsGainedByStudent($group, $student);

    $statuses = [];
    /** @var Assignment $assignment */
    foreach ($group->getAssignments() as $assignment) {
      $best = $this->assignmentSolutions->findBestSolution($assignment, $student);
      $submission = $best ? $best->getLastSubmission() : null;
      $statuses[$assignment->getId()] = $submission ? EvaluationStatus::getStatus($submission) : null;
    }

    return [
      "userId" => $student->getId(),
      "groupId" => $group->getId(),
      "assignments" => [
        "total" => $total,
        "completed" => $completed->count(),
        "missed" => $missed->count()
      ],
      "points" => [
        "total" => $maxPoints,
        "gained" => $gainedPoints
      ],
      "statuses" => $statuses,
      "hasLimit" => $group->getThreshold() !== null && $group->getThreshold() > 0,
      "passesLimit" => $group->getThreshold() === null ? true : $gainedPoints >= $maxPoints * $group->getThreshold()
    ];
  }

}
