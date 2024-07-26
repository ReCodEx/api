<?php

namespace App\Model\Repository;

use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\User;
use App\Model\Entity\Group;
use App\Model\Entity\GroupMembership;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Utils\Arrays;
use DateTime;

/**
 * @extends BaseRepository<AssignmentSolution>
 */
class AssignmentSolutions extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, AssignmentSolution::class);
    }

    /**
     * @param Assignment $assignment
     * @param User $user
     * @return AssignmentSolution[]
     */
    public function findSolutions(Assignment $assignment, User $user): array
    {
        $qb = $this->createQueryBuilder("asol");
        $qb->leftJoin("asol.solution", "sol")
            ->andWhere($qb->expr()->eq("sol.author", ":author"))
            ->andWhere($qb->expr()->eq("asol.assignment", ":assignment"))
            ->setParameter("author", $user->getId())
            ->setParameter("assignment", $assignment->getId())
            ->orderBy("sol.createdAt", "DESC");
        return $qb->getQuery()->getResult();
    }

    /**
     * Get valid submissions for given assignment and user.
     * @param Assignment $assignment
     * @param User $user
     * @return AssignmentSolution[]
     */
    public function findValidSolutions(Assignment $assignment, User $user): array
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
     * Get all solutions of a student from all assignments in a group.
     */
    public function findGroupSolutionsOfStudent(Group $group, User $user): array
    {
        $qb = $this->createQueryBuilder("asol");
        $qb->leftJoin("asol.solution", "sol")
            ->leftJoin("asol.assignment", "ass")
            ->andWhere($qb->expr()->eq("sol.author", ":author"))
            ->andWhere($qb->expr()->eq("ass.group", ":group"))
            ->setParameter("author", $user->getId())
            ->setParameter("group", $group->getId())
            ->orderBy("sol.createdAt", "DESC");
        return $qb->getQuery()->getResult();
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
                    if ($submission === null || $submission->isFailed()) {
                        return false;
                    }

                    if (!$submission->hasEvaluation()) {
                        // Condition sustained for readability
                        // the submission is not evaluated yet, but it will be evaluated (or fail) in the future
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
    private function findValidSolutionsForAssignments(array $assignments, ?User $user = null): array
    {
        if (!$assignments) {
            return []; // this shortcut also prevents assembling wrong DQL
        }

        $assignmentIds = array_map(
            function ($assignment) {
                return $assignment->getId();
            },
            $assignments
        );

        $qb = $this->createQueryBuilder("asol")->leftJoin("asol.solution", "sol");
        $qb->andWhere($qb->expr()->in("asol.assignment", $assignmentIds));
        if ($user !== null) {
            $qb->andWhere("sol.author = :author");
            $qb->setParameter('author', $user->getId());
        }
        $qb->orderBy("sol.createdAt", "DESC");
        $solutions = $qb->getQuery()->getResult();
        return self::filterValidSolutions($solutions);
    }

    /**
     * Local function used to determine the best solution in a collection.
     * @param AssignmentSolution|null $best The best solution found so far
     * @param AssignmentSolution $solution Solution to be compared with the best solution
     * @return AssignmentSolution Better of the two given solutions
     */
    private static function compareBestSolution(
        ?AssignmentSolution $best,
        AssignmentSolution $solution
    ): AssignmentSolution {
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
            } elseif ($createdAtCmp === 0) {
                // finally we compare IDs lexicographically (to be deterministic in very rare cases)
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
     * @return array A nested associative array indexed by author id and by assignment id (nested level)
     *               with values of a solution entity (the best one for the author-assignment)
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
    public function filterBestSolutions(array $solutions): array
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

    /**
     * Return a list of solutions which are under review for a longer time, awaiting its closure.
     * @param DateTime $threshold pending reviews opened before the threshold are listed
     * @return AssignmentSolution[]
     */
    public function findLingeringReviews(DateTime $threshold): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where($qb->expr()->isNull("s.reviewedAt"))
            ->andWhere($qb->expr()->isNotNull("s.reviewStartedAt"))
            ->andWhere($qb->expr()->lt("s.reviewStartedAt", ":threshold"))
            ->setParameter('threshold', $threshold);
        return $qb->getQuery()->getResult();
    }

    /**
     * Return a list of solutions with pending reviews of given teacher.
     * @param User $user who is responsible for the pending reviews (admin/supervisor)
     * @return AssignmentSolution[]
     */
    public function findPendingReviewsOfTeacher(User $user): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb->innerJoin("s.assignment", "a")->innerJoin("a.group", "g")->innerJoin("g.memberships", "gm");
        $qb->where($qb->expr()->eq("gm.user", ":user"))
            ->andWhere($qb->expr()->in("gm.type", [ GroupMembership::TYPE_ADMIN, GroupMembership::TYPE_SUPERVISOR ]))
            ->andWhere($qb->expr()->isNotNull("s.reviewStartedAt"))
            ->andWhere($qb->expr()->isNull("s.reviewedAt"))
            ->setParameter('user', $user->getId());
        return $qb->getQuery()->getResult();
    }

    /**
     * Get statistics about submitted solutions for a particular user and assignment.
     * @param Assignment $assignment
     * @param User $user
     * @return array associative array with 'total', 'evaluated', and 'failed' keys (each holding a counter)
     */
    public function getSolutionStats(Assignment $assignment, User $user): array
    {
        $query = 'WITH selsols (evaluation_id, failure_id) AS (
            SELECT asub.evaluation_id, asub.failure_id FROM solution AS sol
                JOIN assignment_solution AS asol ON asol.solution_id = sol.id
                JOIN assignment_solution_submission AS asub ON asol.last_submission_id = asub.id
                WHERE asol.assignment_id = :assignmentId AND sol.author_id = :authorId
            )
            SELECT
                (SELECT COUNT(*) FROM selsols) AS total,  
                (SELECT COUNT(*) FROM selsols WHERE evaluation_id IS NOT NULL AND failure_id IS NULL) AS evaluated,
                (SELECT COUNT(*) FROM selsols WHERE evaluation_id IS NULL AND failure_id IS NOT NULL) AS failed';

        $conn = $this->em->getConnection();
        $stmt = $conn->prepare($query);
        return $stmt->execute([
            'assignmentId' => $assignment->getId(),
            'authorId' => $user->getId(),
        ])->fetchAssociative();
    }

    /**
     * Get all solutions with review request flag set from one group (optionally filtered for one student).
     * @param Group $group from which the solutions are loaded
     * @param User|null $user if not null, only soliutions of this user will be returned
     * @return AssignmentSolution[]
     */
    public function getReviewRequestSolutions(Group $group, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s')->innerJoin("s.assignment", "a");
        $qb->where($qb->expr()->eq("a.group", ":group"))
            ->andWhere($qb->expr()->eq("s.reviewRequest", 1))
            ->andWhere($qb->expr()->isNull("s.reviewStartedAt"))
            ->setParameter('group', $group->getId());

        if ($user) {
            $qb->innerJoin("s.solution", "sol")
                ->andWhere($qb->expr()->eq("sol.author", ":user"))
                ->setParameter('user', $user->getId());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * As getReviewRequestSolutions(), but returns the result in associative array structure.
     * @param Group $group from which the solutions are loaded
     * @param User|null $user if not null, only soliutions of this user will be returned
     * @return array[] solutions in array hierarchy [userId][assignmentId] -> Solution
     */
    public function getReviewRequestSolutionsIndexed(Group $group, ?User $user = null): array
    {
        $res = [];
        foreach ($this->getReviewRequestSolutions($group, $user) as $solution) {
            $uid = $solution->getSolution()->getAuthor()?->getId();
            $aid = $solution->getAssignment()?->getId();
            if ($uid && $aid) {
                $res[$uid] = $res[$uid] ?? [];
                $res[$uid][$aid] = $solution;
            }
        }
        return $res;
    }
}
