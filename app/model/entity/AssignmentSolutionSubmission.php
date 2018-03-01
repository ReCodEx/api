<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use App\Helpers\EvaluationPointsLoader;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use App\Helpers\EvaluationStatus as ES;


/**
 * @ORM\Entity
 *
 * @method AssignmentSolution getAssignmentSolution()
 */
class AssignmentSolutionSubmission extends Submission implements JsonSerializable, ES\IEvaluable
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


  public function getData(bool $canViewRatios = false, bool $canViewValues = false) {
    $evaluationData = null;
    if ($this->evaluation !== null) {
      $evaluationData = $this->evaluation->getData($canViewRatios, $canViewValues);
    }

    return [
      "id" => $this->id,
      "assignmentSolutionId" => $this->assignmentSolution->getId(),
      "evaluationStatus" => ES\EvaluationStatus::getStatus($this),
      "isCorrect" => $this->isCorrect(),
      "evaluation" => $evaluationData,
      "submittedAt" => $this->submittedAt->getTimestamp(),
      "submittedBy" => $this->submittedBy ? $this->submittedBy->getId() : null
    ];
  }

  public function jsonSerialize() {
    return $this->getData();
  }

  public function __construct(AssignmentSolution $assignmentSolution,
      string $jobConfigPath, User $submittedBy) {
    parent::__construct($submittedBy, $jobConfigPath);
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

  public  function getExercise(): IExercise {
    return $this->getAssignmentSolution()->getAssignment();
  }

  public  function getAuthor(): User {
    return $this->getAssignmentSolution()->getSolution()->getAuthor();
  }
}
