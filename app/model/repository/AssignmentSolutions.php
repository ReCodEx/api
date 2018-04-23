<?php

namespace App\Model\Repository;

use App\Model\Entity\User;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Assignment;


/**
 * @method AssignmentSolution findOrThrow($id)
 */
class AssignmentSolutions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, AssignmentSolution::class);
  }

  /**
   * @param Assignment $assignment
   * @param User $user
   * @return AssignmentSolution[]
   */
  public function findSolutions(Assignment $assignment, User $user) {
    return $this->findBy([
      "solution.author" => $user,
      "assignment" => $assignment
    ], [
      "solution.createdAt" => "DESC"
    ]);
  }

  /**
   * Get valid submissions for given assignment and user.
   * @param Assignment $assignment
   * @param User $user
   * @return AssignmentSolution[]
   */
  public function findValidSolutions(Assignment $assignment, User $user) {
    return $this->findValidSolutionsForAssignments([$assignment], $user);
  }

  /**
   * @param Assignment $assignment
   * @param User $user
   * @return AssignmentSolution|null
   */
  public function findBestSolution(Assignment $assignment, User $user): ?AssignmentSolution {
    return array_reduce(
      $this->findValidSolutions($assignment, $user),
      function (?AssignmentSolution $best, AssignmentSolution $solution) {
        if ($best === null) {
          return $solution;
        }

        if ($best->isAccepted()) {
          return $best;
        }

        if ($solution->isAccepted()) {
          return $solution;
        }

        return $best->getTotalPoints() < $solution->getTotalPoints() ? $solution : $best;
      },
      null
    );
  }

  /**
   * Get valid submissions for given assignments and user.
   * @param Assignment[] $assignments
   * @param User $user
   * @return AssignmentSolution[]
   */
  private function findValidSolutionsForAssignments(array $assignments, User $user) {
    $solutions = $this->findBy([
      "solution.author" => $user,
      "assignment" => $assignments,
    ], [
      "solution.createdAt" => "DESC"
    ]);

    return array_values(array_filter($solutions, function (AssignmentSolution $solution) {
      $submission = $solution->getLastSubmission();
      if ($submission === null || $submission->isFailed() || $submission->getResultsUrl() === null) {
        return false;
      }

      if (!$submission->hasEvaluation()) {
        // Condition sustained for readability
        // the submission is not evaluated yet - suppose it will be evaluated in the future (or marked as invalid)
        // -> otherwise the user would be able to submit many solutions before they are evaluated
        return true;
      }

      return true;
    }));
  }

  /**
   * Find best solutions of given assignments for user.
   * @param Assignment[] $assignments
   * @param User $user
   * @return array list of pairs where first is assignment and second solution, indexed by assignment id
   */
  public function findBestSolutionsForAssignments(array $assignments, User $user) {
    $result = [];
    foreach ($assignments as $assignment) {
      $result[$assignment->getId()] = [$assignment, null];
    }

    $solutions = $this->findValidSolutionsForAssignments($assignments, $user);
    foreach ($solutions as $solution) {
      $assignment = $solution->getAssignment();
      $best = $result[$assignment->getId()][1];

      if ($best === null) {
        $result[$assignment->getId()] = [$assignment, $solution];
        continue;
      }

      if ($best->isAccepted()) {
        continue;
      }

      if ($solution->isAccepted()) {
        $result[$assignment->getId()] = [$assignment, $solution];
        continue;
      }

      if ($best->getTotalPoints() < $solution->getTotalPoints()) {
        $result[$assignment->getId()] = [$assignment, $solution];
      }
    }

    return $result;
  }

}
