<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * Additional identification for files that are accessible to users via stable URLs.
 * The entity always points to a single ExerciseFile, but also follows the CoW principle used for all
 * exercise-assignment records. I.e., when Assignment is created from Exercise, the links are copied
 * as well, but they still point to the same ExerciseFile entities.
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"key", "exercise_id"})})
 */
class ExerciseFileLink
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
     * @ORM\Column(type="string")
     */
    protected $key;

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
     * @param string $key
     * @param ExerciseFile $exerciseFile
     * @param string|null $requiredRole
     * @param Exercise|null $exercise
     * @param Assignment|null $assignment
     */
    private function __construct(
        string $key,
        ExerciseFile $exerciseFile,
        ?string $requiredRole = null,
        ?Exercise $exercise = null,
        ?Assignment $assignment = null,
    ) {
        $this->key = $key;
        $this->requiredRole = $requiredRole;
        $this->exerciseFile = $exerciseFile;
        $this->exercise = $exercise;
        $this->assignment = $assignment;
        $this->createdAt = new DateTime();
    }

    public static function createForExercise(
        string $key,
        ExerciseFile $exerciseFile,
        ?string $requiredRole,
        Exercise $exercise
    ): self {
        return new self($key, $exerciseFile, $requiredRole, $exercise, null);
    }

    public static function copyForAssignment(
        ExerciseFileLink $link,
        Assignment $assignment
    ): self {
        if ($link->getExercise()?->getId() !== $assignment->getExercise()?->getId()) {
            throw new \InvalidArgumentException(
                'Can only copy links associated with an exercise of selected assignment.'
            );
        }
        return new self($link->key, $link->exerciseFile, $link->requiredRole, null, $assignment);
    }

    /*
     * Accessors
     */

    public function getKey(): string
    {
        return $this->key;
    }

    public function getRequiredRole(): ?string
    {
        return $this->requiredRole;
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
