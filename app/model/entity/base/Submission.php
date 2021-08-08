<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use App\Helpers\EvaluationStatus\IEvaluable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
abstract class Submission implements IEvaluable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $submittedAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $submittedBy;

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy->isDeleted() ? null : $this->submittedBy;
    }

    /**
     * @ORM\OneToOne(targetEntity="SolutionEvaluation", cascade={"persist", "remove"}, fetch="EAGER")
     * @var SolutionEvaluation
     */
    protected $evaluation;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isDebug;

    /**
     * @ORM\Column(type="string")
     * Bin subdirectory in paths to related files (job config, results zip).
     * The subdir names are typically time-related (e.g., YYYY-MM) to optimize backup management.
     */
    protected $subdir;


    public function __construct(User $submittedBy, bool $isDebug = false)
    {
        $this->submittedAt = new DateTime();
        $this->submittedBy = $submittedBy;
        $this->isDebug = $isDebug;
        $this->subdir = $this->submittedAt->format('Y-m');
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSubmittedAt(): DateTime
    {
        return $this->submittedAt;
    }

    public function hasEvaluation(): bool
    {
        return $this->evaluation !== null;
    }

    public function getEvaluation(): ?SolutionEvaluation
    {
        return $this->evaluation;
    }

    public function setEvaluation(SolutionEvaluation $evaluation)
    {
        $this->evaluation = $evaluation;
    }

    public function isDebug(): bool
    {
        return $this->isDebug;
    }

    public function getSubdir(): ?string
    {
        return $this->subdir;
    }

    abstract public function getJobType(): string;

    abstract public function getExercise(): ?IExercise;

    abstract public function getAuthor(): ?User;

    abstract public function getSolution(): Solution;
}
