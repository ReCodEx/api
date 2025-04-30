<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * This entity holds records related to all solutions/submissions of one user to one assignment.
 * The entity is created with the first solution (of a user/assignment) submitted.
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"assignment_id", "solver_id"})})
 */
class AssignmentSolver implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * Assignment being solved.
     * @ORM\ManyToOne(targetEntity="Assignment")
     */
    protected $assignment;

    /**
     * User (student) who is attempting to solve the assignment.
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $solver;

    /**
     * A sequence for given assignment and user that counts, how many times a user have submitted a solution.
     * The update of this value is tricky, since it is incremented with every new solution and the value is
     * used to mark the solution (i.e., solution creation and the increment must be in a transaction).
     * @ORM\Column(type="integer")
     */
    protected $lastAttemptIndex;

    /**
     * Counts how many times one of the solutions have been evaluated (including re-evaluations).
     * This counter is mainly for statistical purposes.
     * @ORM\Column(type="integer")
     */
    protected $evaluationsCount;

    /**
     * Initialize entity with default values.
     * @param Assignment $assignment
     * @param User $solver
     * @param int $lastAttemptIndex should be 0, unless we are re-creating an entity
     * @param int $evaluationsCount should be 0, unless we are re-creating an entity
     */
    public function __construct(
        Assignment $assignment,
        User $solver,
        int $lastAttemptIndex = 0,
        int $evaluationsCount = 0
    ) {
        $this->assignment = $assignment;
        $this->solver = $solver;
        $this->lastAttemptIndex = $lastAttemptIndex;
        $this->evaluationsCount = $evaluationsCount;
    }

    public function jsonSerialize(): mixed
    {
        $assignment = $this->getAssignment();
        $solver = $this->getSolver();
        return [
            "id" => $this->getId(),
            "assignmentId" => $assignment ? $assignment->getId() : null,
            "solverId" => $solver ? $solver->getId() : null,
            "lastAttemptIndex" => $this->lastAttemptIndex,
            "evaluationsCount" => $this->evaluationsCount,
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getAssignment(): ?Assignment
    {
        return $this->assignment->isDeleted() ? null : $this->assignment;
    }

    public function getSolver(): ?User
    {
        return $this->solver->isDeleted() ? null : $this->solver;
    }

    public function getLastAttemptIndex(): int
    {
        return $this->lastAttemptIndex;
    }

    public function incrementLastAttemptIndex(): int
    {
        return ++$this->lastAttemptIndex;
    }

    public function getEvaluationsCount(): int
    {
        return $this->evaluationsCount;
    }
}
