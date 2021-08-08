<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use App\Helpers\EvaluationStatus as ES;
use App\Helpers\EvaluationResults as ER;
use App\Model\View\Helpers\SubmissionViewOptions;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="ref_solution_submission_submitted_at_idx", columns={"submitted_at"})})
 */
class ReferenceSolutionSubmission extends Submission implements JsonSerializable, ES\IEvaluable
{
    public const JOB_TYPE = "reference";

    /**
     * @ORM\ManyToOne(targetEntity="ReferenceExerciseSolution", inversedBy="submissions")
     */
    protected $referenceSolution;

    /**
     * @ORM\ManyToOne(targetEntity="HardwareGroup")
     */
    protected $hwGroup;


    public function setEvaluation(SolutionEvaluation $evaluation)
    {
        $this->evaluation = $evaluation;
    }

    /**
     * @ORM\OneToOne(targetEntity="SubmissionFailure", cascade={"persist", "remove"}, inversedBy="referenceSolutionSubmission", fetch="EAGER")
     * @var SubmissionFailure
     */
    protected $failure;

    public function jsonSerialize()
    {
        $evaluationData = null;
        if ($this->evaluation !== null) {
            $options = new SubmissionViewOptions();
            $exercise = $this->referenceSolution->getExercise();
            if ($exercise) {
                $options->initializeExercise($exercise);
            }
            $evaluationData = $this->evaluation->getDataForView($options);
        }

        $failure = $this->getFailure();
        if ($failure && $failure->isConfigErrorFailure()) {
            $failure = $failure->toSimpleArray();
        } else {
            $failure = null;
        }

        return [
            "id" => $this->id,
            "referenceSolutionId" => $this->referenceSolution->getId(),
            "evaluationStatus" => ES\EvaluationStatus::getStatus($this),
            "isCorrect" => $this->isCorrect(),
            "evaluation" => $evaluationData,
            "submittedAt" => $this->submittedAt->getTimestamp(),
            "submittedBy" => $this->submittedBy ? $this->submittedBy->getId() : null,
            "isDebug" => $this->isDebug,
            "failure" => $failure,
        ];
    }

    public function __construct(
        ReferenceExerciseSolution $referenceSolution,
        ?HardwareGroup $hwGroup,
        User $submittedBy,
        bool $isDebug = false
    ) {
        parent::__construct($submittedBy, $isDebug);
        $this->referenceSolution = $referenceSolution;
        $this->hwGroup = $hwGroup;

        $referenceSolution->addSubmission($this);
    }

    /*
     * Accessors
     */

    public function getReferenceSolution(): ReferenceExerciseSolution
    {
        return $this->referenceSolution;
    }

    public function getFailure(): ?SubmissionFailure
    {
        return $this->failure;
    }

    public function setFailure(SubmissionFailure $failure): void
    {
        $this->failure = $failure;
    }

    public function isFailed(): bool
    {
        return $this->failure !== null;
    }

    public function isCorrect(): bool
    {
        return $this->hasEvaluation() && $this->evaluation->getTestResults()->forAll(
            function ($key, TestResult $testResult) {
                $diff = abs($testResult->getScore() - ER\TestResult::SCORE_MAX);
                return $diff < 0.001; // Safe float comparison
            }
        );
    }

    public function getJobType(): string
    {
        return static::JOB_TYPE;
    }

    public function getExercise(): ?IExercise
    {
        return $this->getReferenceSolution()->getExercise();
    }

    public function getAuthor(): ?User
    {
        return $this->getReferenceSolution()->getSolution()->getAuthor();
    }

    public function getSolution(): Solution
    {
        return $this->getReferenceSolution()->getSolution();
    }
}
