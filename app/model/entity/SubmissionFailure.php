<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class SubmissionFailure implements JsonSerializable
{
    use CreateableEntity;

    /**
     * Broker rejected the submission. This happens when there is no worker who can evaluate it.
     */
    public const TYPE_BROKER_REJECT = "broker_reject";

    /**
     * Evaluation failed after the job has been accepted.
     */
    public const TYPE_EVALUATION_FAILURE = "evaluation_failure";

    /**
     * Evaluation finished, but its results could not be loaded
     */
    public const TYPE_LOADING_FAILURE = "loading_failure";

    /**
     * The exercise configuration is invalid and it cannot be compiled
     */
    public const TYPE_CONFIG_ERROR = "config_error";

    /**
     * The exercise configuration is invalid and it cannot be compiled, due to user error
     */
    public const TYPE_SOFT_CONFIG_ERROR = "soft_config_error";

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $type;

    /**
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * @ORM\OneToOne(targetEntity="AssignmentSolutionSubmission", mappedBy="failure")
     */
    protected $assignmentSolutionSubmission;

    /**
     * @ORM\OneToOne(targetEntity="ReferenceSolutionSubmission", mappedBy="failure")
     */
    protected $referenceSolutionSubmission;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var ?DateTime
     */
    protected $resolvedAt;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $resolutionNote;

    /**
     * SubmissionFailure constructor.
     * @param string $type
     * @param string $description
     * @param DateTime|null $createdAt
     */
    private function __construct(string $type, string $description, DateTime $createdAt = null)
    {
        $this->type = $type;
        $this->description = $description;
        $this->createdAt = $createdAt ?: new DateTime();
    }

    public static function create(string $type, string $description, DateTime $createdAt = null)
    {
        return new SubmissionFailure($type, $description, $createdAt);
    }

    /*
     * Accessors
     */

    public function resolve(string $note, DateTime $resolvedAt = null)
    {
        $this->resolvedAt = $resolvedAt ?: new DateTime();
        $this->resolutionNote = $note;
    }

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getResolvedAt(): ?DateTime
    {
        return $this->resolvedAt;
    }

    public function getResolutionNote(): ?string
    {
        return $this->resolutionNote;
    }

    public function getSubmission(): Submission
    {
        return $this->assignmentSolutionSubmission ?? $this->referenceSolutionSubmission;
    }

    public function getAssignmentSolutionSubmission(): ?AssignmentSolutionSubmission
    {
        return $this->assignmentSolutionSubmission;
    }

    /*
     * Accessors
     */

    public function toSimpleArray()
    {
        return [
            "description" => $this->description,
            "createdAt" => $this->createdAt->getTimestamp(),
            "resolvedAt" => $this->resolvedAt ? $this->resolvedAt->getTimestamp() : null,
            "resolutionNote" => $this->resolutionNote
        ];
    }

    public function jsonSerialize(): mixed
    {
        $assignmentSolution = $this->assignmentSolutionSubmission
            ? $this->assignmentSolutionSubmission->getAssignmentSolution() : null;
        $assignment = $assignmentSolution ? $assignmentSolution->getAssignment() : null;
        $referenceSolution = $this->referenceSolutionSubmission
            ? $this->referenceSolutionSubmission->getReferenceSolution() : null;
        $exercise = $referenceSolution ? $referenceSolution->getExercise() : null;

        return [
            "id" => $this->getId(),
            "type" => $this->type,
            "description" => $this->description,
            "createdAt" => $this->createdAt->getTimestamp(),
            "resolvedAt" => $this->resolvedAt ? $this->resolvedAt->getTimestamp() : null,
            "resolutionNote" => $this->resolutionNote,
            "assignmentSolutionId" => $assignmentSolution ? $assignmentSolution->getId() : null,
            "assignmentId" => $assignment ? $assignment->getId() : null,
            "referenceSolutionId" => $referenceSolution ? $referenceSolution->getId() : null,
            "exerciseId" => $exercise ? $exercise->getId() : null
        ];
    }
}
