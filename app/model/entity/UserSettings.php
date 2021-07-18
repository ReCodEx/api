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

    public function __construct(
        bool $darkTheme = true,
        bool $vimMode = false,
        string $defaultLanguage = "en",
        bool $openedSidebar = true,
        bool $useGravatar = true,
        string $defaultPage = null
    ) {
        $this->darkTheme = $darkTheme;
        $this->vimMode = $vimMode;
        $this->defaultLanguage = $defaultLanguage;
        $this->openedSidebar = $openedSidebar;
        $this->useGravatar = $useGravatar;
        $this->defaultPage = $defaultPage;

        $this->newAssignmentEmails = true;
        $this->assignmentDeadlineEmails = true;
        $this->submissionEvaluatedEmails = true;
        $this->solutionCommentsEmails = true;
        $this->assignmentCommentsEmails = true;
        $this->pointsChangedEmails = true;
        $this->assignmentSubmitAfterAcceptedEmails = false;
        $this->assignmentSubmitAfterReviewedEmails = false;
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $darkTheme;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $vimMode;

    /**
     * @ORM\Column(type="string", length=32)
     */
    protected $defaultLanguage;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $openedSidebar;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $useGravatar;

    /**
     * @ORM\Column(type="string", nullable=true)
     * Default page identifier (set and interpreted by the UI only).
     */
    protected $defaultPage = null;


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

    public function jsonSerialize()
    {
        return [
            "darkTheme" => $this->darkTheme,
            "vimMode" => $this->vimMode,
            "defaultLanguage" => $this->defaultLanguage,
            "openedSidebar" => $this->openedSidebar,
            "useGravatar" => $this->useGravatar,
            "defaultPage" => $this->defaultPage,
            "newAssignmentEmails" => $this->newAssignmentEmails,
            "assignmentDeadlineEmails" => $this->assignmentDeadlineEmails,
            "submissionEvaluatedEmails" => $this->submissionEvaluatedEmails,
            "solutionCommentsEmails" => $this->solutionCommentsEmails,
            "assignmentCommentsEmails" => $this->assignmentCommentsEmails,
            "pointsChangedEmails" => $this->pointsChangedEmails,
            "assignmentSubmitAfterAcceptedEmails" => $this->assignmentSubmitAfterAcceptedEmails,
            "assignmentSubmitAfterReviewedEmails" => $this->assignmentSubmitAfterReviewedEmails,
        ];
    }

    ////////////////////////////////////////////////////////////////////////////

    public function getDarkTheme(): bool
    {
        return $this->darkTheme;
    }

    public function setDarkTheme(bool $darkTheme): void
    {
        $this->darkTheme = $darkTheme;
    }

    public function getVimMode(): bool
    {
        return $this->vimMode;
    }

    public function setVimMode(bool $vimMode): void
    {
        $this->vimMode = $vimMode;
    }

    public function getOpenedSidebar(): bool
    {
        return $this->openedSidebar;
    }

    public function setOpenedSidebar(bool $openedSidebar): void
    {
        $this->openedSidebar = $openedSidebar;
    }

    public function getUseGravatar(): bool
    {
        return $this->useGravatar;
    }

    public function setUseGravatar(bool $useGravatar): void
    {
        $this->useGravatar = $useGravatar;
    }

    public function getDefaultPage(): ?string
    {
        return $this->defaultPage;
    }

    public function setDefaultPage(?string $defaultPage): void
    {
        $this->defaultPage = $defaultPage;
    }

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
