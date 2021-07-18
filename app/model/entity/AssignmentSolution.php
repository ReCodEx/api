<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class AssignmentSolution
{
    use FlagAccessor;

    const JOB_TYPE = "student";

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=1024)
     */
    protected $note;

    /**
     * @var ?Assignment
     * @ORM\ManyToOne(targetEntity="Assignment", inversedBy="assignmentSolutions")
     */
    protected $assignment;

    public function getAssignment(): ?Assignment
    {
        return $this->assignment->isDeleted() ? null : $this->assignment;
    }

    /**
     * Determine if submission was made after deadline.
     * @return bool
     */
    public function isAfterDeadline()
    {
        return $this->assignment->isAfterDeadline($this->solution->getCreatedAt());
    }

    public function getMaxPoints()
    {
        return $this->assignment->getMaxPoints($this->solution->getCreatedAt());
    }

    /**
     * @var Solution
     * @ORM\ManyToOne(targetEntity="Solution", cascade={"persist", "remove"}, fetch="EAGER")
     */
    protected $solution;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $accepted;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $reviewed;

    /**
     * @ORM\Column(type="integer")
     */
    protected $bonusPoints;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $overriddenPoints;

    /**
     * Get points acquired by evaluation. If evaluation is not present return null.
     * @return int|null
     */
    public function getPoints(): ?int
    {
        if ($this->overriddenPoints !== null) {
            return $this->overriddenPoints;
        }

        if ($this->lastSubmission === null) {
            return null;
        }

        $evaluation = $this->lastSubmission->getEvaluation();
        if ($evaluation === null) {
            return null;
        }

        return $evaluation->getPoints();
    }

    /**
     * Get total points which this solution got. Including bonus points and points
     * for evaluation.
     * @return int
     */
    public function getTotalPoints()
    {
        $points = $this->getPoints() ?? 0;
        return $points + $this->bonusPoints;
    }

    /**
     * Note that order by annotation has to be present!
     *
     * @ORM\OneToMany(targetEntity="AssignmentSolutionSubmission", mappedBy="assignmentSolution", cascade={"remove"})
     * @ORM\OrderBy({"submittedAt" = "DESC"})
     */
    protected $submissions;

    /**
     * This is a reference to the last (by submittedAt) submission attached to this solution.
     * The reference should speed up loading in many cases since the last submission is the only one that counts.
     * However, in the future, this behavior might be altered, so we can activeley select, which submission is "relevant".
     *
     * @ORM\OneToOne(targetEntity="AssignmentSolutionSubmission", fetch="EAGER")
     * @var AssignmentSolutionSubmission|null
     */
    protected $lastSubmission = null;

    /**
     * @return string[]
     */
    public function getSubmissionsIds(): array
    {
        return $this->submissions->map(
            function (AssignmentSolutionSubmission $submission) {
                return $submission->getId();
            }
        )->getValues();
    }

    /**
     * AssignmentSolution constructor.
     */
    private function __construct()
    {
        $this->accepted = false;
        $this->reviewed = false;
        $this->bonusPoints = 0;
        $this->submissions = new ArrayCollection();
        $this->overriddenPoints = null;
    }

    /**
     * The name of the user
     * @param string $note
     * @param Assignment $assignment
     * @param Solution $solution
     * @return AssignmentSolution
     */
    public static function createSolution(
        string $note,
        Assignment $assignment,
        Solution $solution
    ) {
        $entity = new AssignmentSolution();
        $entity->assignment = $assignment;
        $entity->note = $note;
        $entity->solution = $solution;

        return $entity;
    }

    //////////////////////////////////////////////////////////////////////////////
    /// GETTERS AND SETTERS
    ///

    public function getId(): ?string
    {
        return $this->id;
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function setAccepted(bool $accepted): void
    {
        $this->accepted = $accepted;
    }

    public function isReviewed(): bool
    {
        return $this->reviewed;
    }

    public function setReviewed(bool $reviewed): void
    {
        $this->reviewed = $reviewed;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getSolution(): Solution
    {
        return $this->solution;
    }

    public function getBonusPoints(): int
    {
        return $this->bonusPoints;
    }

    public function setBonusPoints(int $bonusPoints): void
    {
        $this->bonusPoints = $bonusPoints;
    }

    public function getOverriddenPoints(): ?int
    {
        return $this->overriddenPoints;
    }

    public function setOverriddenPoints(?int $overriddenPoints): void
    {
        $this->overriddenPoints = $overriddenPoints;
    }

    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function getLastSubmission(): ?AssignmentSolutionSubmission
    {
        return $this->lastSubmission;
    }

    public function setLastSubmission(?AssignmentSolutionSubmission $lastSubmission): void
    {
        $this->lastSubmission = $lastSubmission;
    }
}
