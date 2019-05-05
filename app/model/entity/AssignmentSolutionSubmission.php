<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use App\Helpers\EvaluationPointsLoader;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use App\Helpers\EvaluationStatus as ES;


/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="assignment_solution_submission_submitted_at_idx", columns={"submitted_at"})})
 *
 * @method AssignmentSolution getAssignmentSolution()
 * @method SubmissionFailure getFailure()
 * @method setFailure(SubmissionFailure $failure)
 */
class AssignmentSolutionSubmission extends Submission implements ES\IEvaluable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  const JOB_TYPE = "student";

  /**
   * @ORM\ManyToOne(targetEntity="AssignmentSolution", inversedBy="submissions")
   */
  protected $assignmentSolution;

  /**
   * @ORM\OneToOne(targetEntity="SubmissionFailure", cascade={"persist", "remove"}, inversedBy="assignmentSolutionSubmission", fetch="EAGER")
   * @var SubmissionFailure
   */
  protected $failure;


  public function __construct(AssignmentSolution $assignmentSolution,
      string $jobConfigPath, User $submittedBy, bool $isDebug = false) {
    parent::__construct($submittedBy, $jobConfigPath, $isDebug);
    $this->assignmentSolution = $assignmentSolution;
  }

  function isFailed(): bool {
    return $this->failure !== null;
  }

  function isCorrect(): bool {
    return EvaluationPointsLoader::isStudentCorrect($this);
  }

  public function getJobType(): string {
    return static::JOB_TYPE;
  }

  public  function getExercise(): ?IExercise {
    return $this->getAssignmentSolution()->getAssignment();
  }

  public  function getAuthor(): ?User {
    return $this->getAssignmentSolution()->getSolution()->getAuthor();
  }
}
