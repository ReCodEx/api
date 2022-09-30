<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class UserSettings implements JsonSerializable
{
    use FlagAccessor;

    public function __construct(string $defaultLanguage = "en")
    {
        $this->defaultLanguage = $defaultLanguage;

        $this->newAssignmentEmails = true;
        $this->assignmentDeadlineEmails = true;
        $this->submissionEvaluatedEmails = true;
        $this->solutionCommentsEmails = true;
        $this->solutionReviewsEmails = true;
        $this->assignmentCommentsEmails = true;
        $this->pointsChangedEmails = true;
        $this->assignmentSubmitAfterAcceptedEmails = false;
        $this->assignmentSubmitAfterReviewedEmails = false;
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    protected $defaultLanguage;


    /*******************
     * Emails settings *
     *******************/

    /**
     * @ORM\Column(type="boolean")
     */
    protected $newAssignmentEmails;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $assignmentDeadlineEmails;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $submissionEvaluatedEmails;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $solutionCommentsEmails;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $solutionReviewsEmails;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $assignmentCommentsEmails;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $pointsChangedEmails;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $assignmentSubmitAfterAcceptedEmails;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $assignmentSubmitAfterReviewedEmails;

    public function jsonSerialize(): mixed
    {
        return [
            "defaultLanguage" => $this->defaultLanguage,
            "newAssignmentEmails" => $this->newAssignmentEmails,
            "assignmentDeadlineEmails" => $this->assignmentDeadlineEmails,
            "submissionEvaluatedEmails" => $this->submissionEvaluatedEmails,
            "solutionCommentsEmails" => $this->solutionCommentsEmails,
            "solutionReviewsEmails" => $this->solutionReviewsEmails,
            "assignmentCommentsEmails" => $this->assignmentCommentsEmails,
            "pointsChangedEmails" => $this->pointsChangedEmails,
            "assignmentSubmitAfterAcceptedEmails" => $this->assignmentSubmitAfterAcceptedEmails,
            "assignmentSubmitAfterReviewedEmails" => $this->assignmentSubmitAfterReviewedEmails,
        ];
    }

    /*
     * Accessors
     */

    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(string $defaultLanguage): void
    {
        $this->defaultLanguage = $defaultLanguage;
    }

    public function getNewAssignmentEmails(): bool
    {
        return $this->newAssignmentEmails;
    }

    public function setNewAssignmentEmails(bool $newAssignmentEmails): void
    {
        $this->newAssignmentEmails = $newAssignmentEmails;
    }

    public function getAssignmentDeadlineEmails(): bool
    {
        return $this->assignmentDeadlineEmails;
    }

    public function setAssignmentDeadlineEmails(bool $assignmentDeadlineEmails): void
    {
        $this->assignmentDeadlineEmails = $assignmentDeadlineEmails;
    }

    public function getSubmissionEvaluatedEmails(): bool
    {
        return $this->submissionEvaluatedEmails;
    }

    public function setSubmissionEvaluatedEmails(bool $submissionEvaluatedEmails): void
    {
        $this->submissionEvaluatedEmails = $submissionEvaluatedEmails;
    }

    public function getSolutionCommentsEmails(): bool
    {
        return $this->solutionCommentsEmails;
    }

    public function setSolutionCommentsEmails(bool $solutionCommentsEmails): void
    {
        $this->solutionCommentsEmails = $solutionCommentsEmails;
    }

    public function getSolutionReviewsEmails(): bool
    {
        return $this->solutionReviewsEmails;
    }

    public function setSolutionReviewsEmails(bool $solutionReviewsEmails): void
    {
        $this->solutionReviewsEmails = $solutionReviewsEmails;
    }

    public function getAssignmentCommentsEmails(): bool
    {
        return $this->assignmentCommentsEmails;
    }

    public function setAssignmentCommentsEmails(bool $assignmentCommentsEmails): void
    {
        $this->assignmentCommentsEmails = $assignmentCommentsEmails;
    }

    public function getPointsChangedEmails(): bool
    {
        return $this->pointsChangedEmails;
    }

    public function setPointsChangedEmails(bool $pointsChangedEmails): void
    {
        $this->pointsChangedEmails = $pointsChangedEmails;
    }

    public function getAssignmentSubmitAfterAcceptedEmails(): bool
    {
        return $this->assignmentSubmitAfterAcceptedEmails;
    }

    public function setAssignmentSubmitAfterAcceptedEmails(bool $assignmentSubmitAfterAcceptedEmails): void
    {
        $this->assignmentSubmitAfterAcceptedEmails = $assignmentSubmitAfterAcceptedEmails;
    }

    public function getAssignmentSubmitAfterReviewedEmails(): bool
    {
        return $this->assignmentSubmitAfterReviewedEmails;
    }

    public function setAssignmentSubmitAfterReviewedEmails(bool $assignmentSubmitAfterReviewedEmails): void
    {
        $this->assignmentSubmitAfterReviewedEmails = $assignmentSubmitAfterReviewedEmails;
    }
}
