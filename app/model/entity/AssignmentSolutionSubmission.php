<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use App\Helpers\EvaluationPointsLoader;
use Doctrine\ORM\Mapping as ORM;
use App\Helpers\EvaluationStatus as ES;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="assignment_solution_submission_submitted_at_idx", columns={"submitted_at"})})
 */
class AssignmentSolutionSubmission extends Submission implements ES\IEvaluable
{
    public const JOB_TYPE = "student";

    /**
     * @ORM\ManyToOne(targetEntity="AssignmentSolution", inversedBy="submissions")
     */
    protected $assignmentSolution;

    /**
     * @ORM\OneToOne(targetEntity="SubmissionFailure", cascade={"persist", "remove"},
     *               inversedBy="assignmentSolutionSubmission", fetch="EAGER")
     * @var SubmissionFailure
     */
    protected $failure;


    public function __construct(
        AssignmentSolution $assignmentSolution,
        User $submittedBy,
        bool $isDebug = false
    ) {
        parent::__construct($submittedBy, $isDebug);
        $this->assignmentSolution = $assignmentSolution;
        if ($assignmentSolution->getLastSubmission() === null) {
            // this is mainly for the fixtures, the caller is responsible for updating last submission anyway
            $assignmentSolution->setLastSubmission($this);
        }
    }

    /*
     * Accessors
     */

    public function getAssignmentSolution(): ?AssignmentSolution
    {
        return $this->assignmentSolution;
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
        return EvaluationPointsLoader::isStudentCorrect($this);
    }

    public function getJobType(): string
    {
        return static::JOB_TYPE;
    }

    public function getExercise(): ?IExercise
    {
        return $this->getAssignmentSolution()->getAssignment();
    }

    public function getAuthor(): ?User
    {
        return $this->getAssignmentSolution()->getSolution()->getAuthor();
    }

    public function getSolution(): Solution
    {
        return $this->getAssignmentSolution()->getSolution();
    }
}
