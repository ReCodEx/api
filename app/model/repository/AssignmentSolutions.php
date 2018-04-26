<?php

namespace App\Model\Repository;

use App\Helpers\Pair;
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
    return $this->findBestSolutionsForAssignments([$assignment], $user)[$assignment->getId()]->value;
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
   * @return Pair[] An associative array (indexed by assignment id) of pairs where the first item is
   * an assignment entity and the second is a solution entity (the best one for the assignment)
   */
  public function findBestSolutionsForAssignments(array $assignments, User $user): array {
    $result = [];
    foreach ($assignments as $assignment) {
      $result[$assignment->getId()] = new Pair($assignment, null);
    }

    $solutions = $this->findValidSolutionsForAssignments($assignments, $user);
    foreach ($solutions as $solution) {
      $assignment = $solution->getAssignment();
      $best = $result[$assignment->getId()]->value;

      if ($best === null) {
        $result[$assignment->getId()]->value = $solution;
        continue;
      }

      if ($best->isAccepted()) {
        continue;
      }

      if ($solution->isAccepted()) {
        $result[$assignment->getId()]->value = $solution;
        continue;
      }

      if ($best->getTotalPoints() < $solution->getTotalPoints()) {
        $result[$assignment->getId()]->value = $solution;
      }
    }

    return $result;
  }

}
