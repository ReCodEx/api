<?php

namespace App\Model\Repository;

use App\Model\Entity\AssignmentSolver;
use App\Model\Entity\Assignment;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Exceptions\InternalServerException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<AssignmentSolver>
 */
class AssignmentSolvers extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, AssignmentSolver::class);
    }

    /**
     * Find solvers of a particular assignment (optionally of only one user).
     * @param Assignment $assignment
     * @param User|null $solver
     * @return AssignmentSolver[] that matches the criteria
     */
    public function findInAssignment(Assignment $assignment, ?User $solver = null): array
    {
        $constraints = ["assignment" => $assignment];
        if ($solver) {
            $constraints["solver"] = $solver;
        }
        return $this->findBy($constraints);
    }

    /**
     * Find solvers of all assignment of a particular group (optionally of only one user).
     * @param Group $group
     * @param User|null $solver
     * @return AssignmentSolver[] that matches the criteria
     */
    public function findInGroup(Group $group, ?User $solver = null): array
    {
        $qb = $this->repository->createQueryBuilder("s");
        $qb->leftJoin("s.assignment", "a");
        $qb->andWhere($qb->expr()->eq("a.group", ":group"))
            ->setParameter("group", $group->getId());

        if ($solver) {
            $qb->andWhere($qb->expr()->eq("s.solver", ':solver'))
                ->setParameter("solver", $solver->getId());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Gets incremented value of last attempt index for given assignment solver.
     * The entity is created if not exists.
     * This method is assumed to be called in a transaction!
     * @param Assignment $assignment
     * @param User $solver
     * @return int new last attempt index (that can be used for a new solution)
     */
    public function getNextAttemptIndex(Assignment $assignment, User $solver): int
    {
        $candidates = $this->findBy(["assignment" => $assignment, "solver" => $solver]);
        if (count($candidates) > 1) {
            // ooops, something is very wrong since unique index should prevent that
            throw new InternalServerException("Database integrity constraints have failed.");
        }

        if (count($candidates) === 1) {
            $assignmentSolver = reset($candidates);
        } else {
            $assignmentSolver = new AssignmentSolver($assignment, $solver);
        }

        // increment the index and return new value
        $index = $assignmentSolver->incrementLastAttemptIndex();
        $this->persist($assignmentSolver);
        return $index;
    }

    /**
     * Increment the evaluation counter for given assignment solver record.
     * The record is expected to exist, otherwise nothing is updated.
     * @param Assignment $assignment
     * @param User $solver
     */
    public function incrementEvaluationsCount(Assignment $assignment, User $solver): void
    {
        $query = $this->em->createQuery("
            UPDATE App\Model\Entity\AssignmentSolver s
            SET s.evaluationsCount = s.evaluationsCount + 1
            WHERE IDENTITY(s.assignment) = :assignmentId AND IDENTITY(s.solver) = :solverId
        ");
        $query->setParameters(["assignmentId" => $assignment->getId(), "solverId" => $solver->getId()]);
        $query->execute();
    }

    /**
     * @return int a total number of submitted solutions (including deleted ones)
     */
    public function getTotalSolutionsCount(): int
    {
        $qb = $this->createQueryBuilder("s")->select('SUM(s.lastAttemptIndex)');
        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return int a total number of evaluations (including deleted ones)
     * Note: submissions that failed before evaluation started are not counted.
     */
    public function getTotalEvaluationsCount(): int
    {
        $qb = $this->createQueryBuilder("s")->select('SUM(s.evaluationsCount)');
        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
