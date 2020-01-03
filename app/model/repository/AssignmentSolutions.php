<?php

namespace App\Model\Repository;

use App\Helpers\Pair;
use App\Model\Entity\User;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Assignment;
use Nette\Utils\Arrays;

/**
 * @method AssignmentSolution findOrThrow($id)
 * @method AssignmentSolution get($id)
 */
class AssignmentSolutions extends BaseRepository
{

    public function __construct(EntityManager $em)
    {
        parent::__construct($em, AssignmentSolution::class);
    }

    /**
     * @param Assignment $assignment
     * @param User $user
     * @return AssignmentSolution[]
     */
    public function findSolutions(Assignment $assignment, User $user)
    {
        return $this->findBy(
            [
                "solution.author" => $user,
                "assignment" => $assignment
            ],
            [
                "solution.createdAt" => "DESC"
            ]
        );
    }

    /**
     * Get valid submissions for given assignment and user.
     * @param Assignment $assignment
     * @param User $user
     * @return AssignmentSolution[]
     */
    public function findValidSolutions(Assignment $assignment, User $user)
    {
        return $this->findValidSolutionsForAssignments([$assignment], $user);
    }

    /**
     * @param Assignment $assignment
     * @param User $user
     * @return AssignmentSolution|null
     */
    public function findBestSolution(Assignment $assignment, User $user): ?AssignmentSolution
    {
        $solutions = $this->findBestUserSolutionsForAssignments([$assignment], $user);
        return Arrays::get($solutions, $assignment->getId(), null);
    }

    /**
     * Helper function that filters array of solutions so only valid solutions remain.
     * @param AssignmentSolution[] $solutions
     * @return AssignmentSolution[]
     */
    private static function filterValidSolutions(array $solutions): array
    {
        return array_values(
            array_filter(
                $solutions,
                function (AssignmentSolution $solution) {
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
                }
            )
        );
    }

    /**
     * Get valid submissions for given assignments and user.
     * @param Assignment[] $assignments
     * @param User|null $user If null, solutions of all users are returned
     * @return AssignmentSolution[]
     */
    private function findValidSolutionsForAssignments(array $assignments, ?User $user = null)
    {
        $findBy = ["assignment" => $assignments];
        if ($user !== null) {
            $findBy["solution.author"] = $user;
        }
        $solutions = $this->findBy($findBy);
        return self::filterValidSolutions($solutions);
    }

    /**
     * Local function used to determine the best solution in a collection.
     * @param AssignmentSolution|null $best The best solution found so far
     * @param AssignmentSolution $solution Solution to be compared with the best solution
     * @return AssignmentSolution Better of the two given solutions
     */
    private static function compareBestSolution(?AssignmentSolution $best, AssignmentSolution $solution)
    {
        if ($best === null) {
            return $solution;
        }

        if ($best->isAccepted()) {
            return $best;
        }

        if ($solution->isAccepted()) {
            return $solution;
        }

        $pointsCmp = $best->getTotalPoints() <=> $solution->getTotalPoints();
        if ($pointsCmp === -1) {  // first we compare points
            return $solution;
        } elseif ($pointsCmp === 0) { // then time of creation
            $createdAtCmp = $best->getSolution()->getCreatedAt() <=> $solution->getSolution()->getCreatedAt();
            if ($createdAtCmp === -1) {
                return $solution;
            } elseif ($createdAtCmp === 0) { // finally we compare IDs lexicographically (to be deterministic in very rare cases)
                if (($best->getId() <=> $solution->getId()) === -1) {
                    return $solution;
                }
            }
        }

        return $best;
    }

    /**
     * Find best solutions of given assignments (for all users).
     * @param Assignment[] $assignments
     * @return AssignmentSolution[] A nested associative array indexed by author id and by assignment id (nested level)
     *                              with values of a solution entity (the best one for the author-assignment)
     */
    public function findBestSolutionsForAssignments(array $assignments): array
    {
        $result = [];
        $solutions = $this->findValidSolutionsForAssignments($assignments); // for all users
        foreach ($solutions as $solution) {
            $assignment = $solution->getAssignment();
            if ($assignment === null) {
                continue;
            }
            $assignmentId = $assignment->getId();

            $author = $solution->getSolution()->getAuthor();
            if ($author === null) {
                continue;
            }
            $authorId = $author->getId();

            if (!array_key_exists($authorId, $result)) {
                $result[$authorId] = [];
            }

            $best = Arrays::get($result[$authorId], $assignmentId, null);
            $result[$authorId][$assignmentId] = self::compareBestSolution($best, $solution);
        }

        return $result;
    }

    /**
     * Find best solutions of given assignments for user.
     * @param Assignment[] $assignments
     * @param User $user
     * @return AssignmentSolution[] An associative array indexed by assignment id
     * with values of a solution entity (the best one for the assignment)
     */
    public function findBestUserSolutionsForAssignments(array $assignments, User $user): array
    {
        $result = [];
        $solutions = $this->findValidSolutionsForAssignments($assignments, $user);
        foreach ($solutions as $solution) {
            $assignment = $solution->getAssignment();
            if ($assignment === null) {
                continue;
            }

            $best = Arrays::get($result, $assignment->getId(), null);
            $result[$assignment->getId()] = self::compareBestSolution($best, $solution);
        }

        return $result;
    }

    /**
     * Filter the given *complete* array of solutions so that only best solutions remain.
     * Term *complete* means that if solution S of assignment A and user U is in the input set,
     * all solutions from A by user U are also in the set.
     * @param AssignmentSolution[] $solutions
     * @return AssignmentSolution[]
     */
    public function filterBestSolutions(array $solutions)
    {
        $result = [];
        $validSolutions = self::filterValidSolutions($solutions);
        foreach ($validSolutions as $solution) {
            $assignment = $solution->getAssignment();
            if ($assignment === null) {
                continue;
            }

            $author = $solution->getSolution()->getAuthor();
            if ($author === null) {
                continue;
            }

            // composed key (we need uniqueness, and do not want to create nested arrays)
            $key = $assignment->getId() . ':' . $author->getId();

            $best = Arrays::get($result, $key, null);
            $result[$key] = self::compareBestSolution($best, $solution);
        }

        return array_values($result);
    }
}
