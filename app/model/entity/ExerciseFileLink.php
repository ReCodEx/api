<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;

/**
 * Additional identification for files that are accessible to users via stable URLs.
 * The entity always points to a single ExerciseFile, but also follows the CoW principle used for all
 * exercise-assignment records. I.e., when Assignment is created from Exercise, the links are copied
 * as well, but they still point to the same ExerciseFile entities.
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"key", "exercise_id"})})
 */
class ExerciseFileLink implements JsonSerializable
{
    use CreatableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * The key (fixed ID) used to identify the file in exercise specification (for simple replacement).
     * @ORM\Column(type="string", length=16)
     */
    protected $key;

    /**
     * New name under which the file is downloaded (null means the original name).
     * @ORM\Column(type="string", nullable=true)
     */
    protected $saveName;

    /**
     * Minimal required user role to access the file (null means even non-logged-in users).
     * @ORM\Column(type="string", nullable=true)
     */
    protected $requiredRole;


    /**
     * @ORM\ManyToOne(targetEntity="ExerciseFile", inversedBy="links", cascade={"persist", "remove"})
     */
    protected $exerciseFile;

    /**
     * @ORM\ManyToOne(targetEntity="Exercise", inversedBy="fileLinks")
     */
    protected $exercise;

    /**
     * @ORM\ManyToOne(targetEntity="Assignment", inversedBy="fileLinks")
     */
    protected $assignment;

    /**
     * Link constructor
     * @param string $key used to identify the file in exercise specification (for simple replacement)
     * @param ExerciseFile $exerciseFile
     * @param Exercise|null $exercise
     * @param Assignment|null $assignment
     * @param string|null $requiredRole minimal required user role to access the file (null = non-logged-in users)
     * @param string|null $saveName new name under which the file is downloaded (null means the original name)
     */
    private function __construct(
        string $key,
        ExerciseFile $exerciseFile,
        ?Exercise $exercise = null,
        ?Assignment $assignment = null,
        ?string $requiredRole = null,
        ?string $saveName = null
    ) {
        $this->key = $key;
        $this->requiredRole = $requiredRole;
        $this->saveName = $saveName;
        $this->exerciseFile = $exerciseFile;
        $this->exercise = $exercise;
        $this->assignment = $assignment;
        $this->createdAt = new DateTime();
    }

    /**
     * Create a link for exercise
     * @param string $key
     * @param ExerciseFile $exerciseFile
     * @param Exercise $exercise
     * @param string|null $requiredRole
     * @param string|null $saveName
     */
    public static function createForExercise(
        string $key,
        ExerciseFile $exerciseFile,
        Exercise $exercise,
        ?string $requiredRole = null,
        ?string $saveName = null
    ): self {
        return new self($key, $exerciseFile, $exercise, null, $requiredRole, $saveName);
    }

    /**
     * Create a link for assignment by copying an existing link
     * @param ExerciseFileLink $link to be copied when assignment is being created
     * @param Assignment $assignment the assignment for which the link is being created
     */
    public static function copyForAssignment(
        ExerciseFileLink $link,
        Assignment $assignment
    ): self {
        if ($link->getExercise()?->getId() !== $assignment->getExercise()?->getId()) {
            throw new \InvalidArgumentException(
                'Can only copy links associated with an exercise of selected assignment.'
            );
        }
        return new self($link->key, $link->exerciseFile, null, $assignment, $link->requiredRole, $link->saveName);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'key' => $this->getKey(),
            'requiredRole' => $this->getRequiredRole(),
            'saveName' => $this->getSaveName(),
            'exerciseFileId' => $this->exerciseFile->getId(),
            'createdAt' => $this->createdAt->getTimestamp(),
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }
    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getRequiredRole(): ?string
    {
        return $this->requiredRole;
    }

    public function setRequiredRole(?string $requiredRole): void
    {
        $this->requiredRole = $requiredRole;
    }

    public function getSaveName(): ?string
    {
        return $this->saveName;
    }

    public function setSaveName(?string $saveName): void
    {
        $this->saveName = $saveName;
    }

    public function getExerciseFile(): ExerciseFile
    {
        return $this->exerciseFile;
    }

    public function getExercise(): ?Exercise
    {
        return $this->exercise;
    }

    public function getAssignment(): ?Assignment
    {
        return $this->assignment;
    }
}
