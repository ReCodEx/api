<?php

namespace App\Model\Entity;

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
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  const JOB_TYPE = "student";

  /**
   * @ORM\ManyToOne(targetEntity="AssignmentSolution", inversedBy="submissions")
   */
  protected $assignmentSolution;

  /**
   * @ORM\OneToOne(targetEntity="SolutionEvaluation", inversedBy="assignmentSolutionSubmission", cascade={"persist", "remove"})
   * @var SolutionEvaluation
   */
  protected $evaluation;

  public function hasEvaluation(): bool {
    return $this->evaluation !== NULL;
  }

  public function getEvaluation(): ?SolutionEvaluation {
    return $this->evaluation;
  }

  public function setEvaluation(SolutionEvaluation $evaluation) {
    $this->evaluation = $evaluation;
  }

  /**
   * @var Collection
   * @ORM\OneToMany(targetEntity="SubmissionFailure", mappedBy="assignmentSolutionSubmission")
   */
  protected $failures;


  public function jsonSerialize() {
    $evaluationData = NULL;
    if ($this->evaluation !== NULL) {
      $evaluationData = $this->evaluation->getData(TRUE, TRUE);
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
    return  EvaluationPointsLoader::isStudentCorrect($this->evaluation);
  }

}
