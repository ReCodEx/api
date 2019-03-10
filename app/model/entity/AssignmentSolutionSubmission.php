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
 *
 * @method AssignmentSolution getAssignmentSolution()
 * @method Collection getFailures()
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
   * @var Collection
   * @ORM\OneToMany(targetEntity="SubmissionFailure", mappedBy="assignmentSolutionSubmission", cascade={"remove"})
   */
  protected $failures;


  public function __construct(AssignmentSolution $assignmentSolution,
      string $jobConfigPath, User $submittedBy, bool $isDebug = false) {
    parent::__construct($submittedBy, $jobConfigPath, $isDebug);
    $this->assignmentSolution = $assignmentSolution;
    $this->failures = new ArrayCollection();
  }

  function isFailed(): bool {
    return $this->failures->count() > 0;
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
