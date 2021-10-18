<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 */
class ReferenceExerciseSolution
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
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
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
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
}
