<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;

/**
 * @ORM\Entity
 */
class ReferenceExerciseSolution
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
     * @ORM\ManyToOne(targetEntity="Exercise", inversedBy="referenceSolutions")
     */
    protected $exercise;

    public function getExercise(): ?Exercise
    {
        return $this->exercise->isDeleted() ? null : $this->exercise;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * @ORM\OneToOne(targetEntity="Solution", cascade={"persist", "remove"}, fetch="EAGER")
     */
    protected $solution;

    /**
     * @ORM\OneToMany(targetEntity="ReferenceSolutionSubmission", mappedBy="referenceSolution", cascade={"remove"})
     */
    protected $submissions;

    /**
     * This is a reference to the last (by submittedAt) submission attached to this solution.
     * The reference should speed up loading in many cases since the last submission is the only one that counts.
     * However, this behavior might be altered in the future, so we can actively select which submission is relevant.
     *
     * @ORM\OneToOne(targetEntity="ReferenceSolutionSubmission", fetch="EAGER")
     * @var ReferenceSolutionSubmission|null
     */
    protected $lastSubmission = null;

    public const VISIBILITY_PROMOTED = 2;
    public const VISIBILITY_PUBLIC = 1;
    public const VISIBILITY_PRIVATE = 0;
    public const VISIBILITY_TEMP = -1;

    /**
     * Visibility is extended boolean. Values > 0 mean, the solution is public, otherwise it is private.
     * Private temp denotes the solution should also be garbage collected in the future.
     * Promoted solutions are public ones explicitly marked as "you should see this"
     * (e.g., a sample solution of the author of the exercise).
     * @ORM\Column(type="integer")
     */
    protected $visibility = 0;

    /**
     * Add submission to solution entity.
     * @param ReferenceSolutionSubmission $submission
     */
    public function addSubmission(ReferenceSolutionSubmission $submission)
    {
        $this->submissions->add($submission);
    }

    public function __construct(Exercise $exercise, User $user, string $description, RuntimeEnvironment $runtime)
    {
        $this->exercise = $exercise;
        $this->description = $description;
        $this->solution = new Solution($user, $runtime);
        $this->submissions = new ArrayCollection();
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getSolution(): Solution
    {
        return $this->solution;
    }

    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function getLastSubmission(): ?ReferenceSolutionSubmission
    {
        return $this->lastSubmission;
    }

    public function setLastSubmission(?ReferenceSolutionSubmission $lastSubmission): void
    {
        $this->lastSubmission = $lastSubmission;
    }

    public function getVisibility(): int
    {
        return $this->visibility;
    }

    public function setVisibility(int $visibility): void
    {
        if ($visibility > self::VISIBILITY_PROMOTED || $visibility < self::VISIBILITY_TEMP) {
            throw new InvalidArgumentException("Visibility value out of range.");
        }
        $this->visibility = $visibility;
    }
}
