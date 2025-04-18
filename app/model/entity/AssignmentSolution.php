<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * @ORM\Entity
 */
class AssignmentSolution
{
    use FlagAccessor;

    public const JOB_TYPE = "student";

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
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
     * Determine if submission was made after deadline (the last one).
     * @return bool
     */
    public function isAfterDeadline(): bool
    {
        return $this->assignment->isAfterDeadline($this->solution->getCreatedAt());
    }

    /**
     * Determine if the submission was made after the first deadline.
     */
    public function isAfterFirstDeadline(): bool
    {
        return $this->assignment->isAfterFirstDeadline($this->solution->getCreatedAt());
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
     * If true, the student is requesting a code review for this solution.
     * One solution of one solver at most may have this flag set (similarly to accepted flag).
     * The flag is automatically reset when review is saved.
     */
    protected $reviewRequest = false;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * Time when a review was started.
     */
    protected $reviewStartedAt = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * Time when a review was closed (and the students were duly notified the comments are complete).
     * Not null value also marks the solution as "reviewed" (replaces previous bool flag).
     */
    protected $reviewedAt = null;

    /**
     * @ORM\Column(type="integer")
     * Number of issues made in review comments. This is an aggregated cached value so it can be accessed faster.
     * The number of issues is computed when (and updated after) the review is closed.
     * When the review is open, the number of issues is kept 0, regardless of how many issue comments exist.
     */
    protected $issues = 0;

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
     * However, this behavior might be altered in the future, so we can actively select which submission is relevant.
     *
     * @ORM\OneToOne(targetEntity="AssignmentSolutionSubmission", fetch="EAGER", cascade={"persist"})
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
     * Incremental counter marking submission attempts of one user solving one assignment (one-based).
     * Each solution gets an unique marker (per user x assignment) which is immutable,
     * so it will hold even when some solutions get deleted.
     * @ORM\Column(type="integer")
     */
    protected $attemptIndex;

    /**
     * @ORM\OneToMany(targetEntity="ReviewComment", mappedBy="solution")
     */
    protected $reviewComments;

    /**
     * @var PlagiarismDetectionBatch|null
     * @ORM\ManyToOne(targetEntity="PlagiarismDetectionBatch")
     * Refers to last plagiarism detection batch, in which similarities were detected for this solution.
     * If null, no similarities were detected (yet).
     */
    protected $plagiarismBatch = null;

    /**
     * AssignmentSolution constructor.
     */
    private function __construct()
    {
        $this->accepted = false;
        $this->reviewRequest = false;
        $this->bonusPoints = 0;
        $this->submissions = new ArrayCollection();
        $this->overriddenPoints = null;
        $this->reviewComments = new ArrayCollection();
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
        Solution $solution,
        int $attemptIndex,
    ) {
        $entity = new AssignmentSolution();
        $entity->assignment = $assignment;
        $entity->note = $note;
        $entity->solution = $solution;
        $entity->attemptIndex = $attemptIndex;
        return $entity;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function setAccepted(bool $accepted): void
    {
        $this->accepted = $accepted;
    }

    public function isReviewRequested(): bool
    {
        return $this->reviewRequest;
    }

    public function setReviewRequest(bool $request = true): void
    {
        $this->reviewRequest = $request;
    }

    public function canSetReviewRequest(bool $request): bool
    {
        return !$request || !$this->isReviewed();
    }

    public function isReviewed(): bool
    {
        return $this->reviewedAt !== null;
    }

    public function getReviewedAt(): ?DateTime
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?DateTime $reviewedAt): void
    {
        $this->reviewedAt = $reviewedAt;
        if ($reviewedAt) {
            $this->reviewRequest = false;
        }
    }

    public function getReviewStartedAt(): ?DateTime
    {
        return $this->reviewStartedAt;
    }

    public function setReviewStartedAt(?DateTime $reviewStartedAt): void
    {
        $this->reviewStartedAt = $reviewStartedAt;
    }

    public function getIssuesCount(): int
    {
        return $this->issues;
    }

    public function setIssuesCount(int $issues): void
    {
        $this->issues = $issues;
    }

    public function getReviewComments(): Collection
    {
        return $this->reviewComments;
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

    public function getAttemptIndex(): int
    {
        return $this->attemptIndex;
    }

    public function getPlagiarismBatch(): ?PlagiarismDetectionBatch
    {
        return $this->plagiarismBatch;
    }

    public function setPlagiarismBatch(?PlagiarismDetectionBatch $batch = null)
    {
        $this->plagiarismBatch = $batch;
    }
}
