<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use App\Helpers\EvaluationStatus as ES;
use App\Helpers\EvaluationResults as ER;

/**
 * @ORM\Entity
 *
 * @method ReferenceExerciseSolution getReferenceSolution()
 */
class ReferenceSolutionSubmission extends Submission implements JsonSerializable, ES\IEvaluable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  const JOB_TYPE = "reference";

  /**
   * @ORM\ManyToOne(targetEntity="ReferenceExerciseSolution", inversedBy="submissions")
   */
  protected $referenceSolution;

  /**
   * @ORM\ManyToOne(targetEntity="HardwareGroup")
   */
  protected $hwGroup;


  public function setEvaluation(SolutionEvaluation $evaluation) {
    $this->evaluation = $evaluation;
  }

  /**
   * @var Collection
   * @ORM\OneToMany(targetEntity="SubmissionFailure", mappedBy="referenceSolutionSubmission")
   */
  protected $failures;


  public function jsonSerialize() {
    $evaluationData = NULL;
    if ($this->evaluation !== NULL) {
      $evaluationData = $this->evaluation->getData(TRUE, TRUE);
    }

    return [
      "id" => $this->id,
      "referenceSolutionId" => $this->referenceSolution->getId(),
      "evaluationStatus" => ES\EvaluationStatus::getStatus($this),
      "isCorrect" => $this->isCorrect(),
      "evaluation" => $evaluationData,
      "submittedAt" => $this->submittedAt->getTimestamp(),
      "submittedBy" => $this->submittedBy ? $this->submittedBy->getId() : null
    ];
  }

  public function __construct(ReferenceExerciseSolution $referenceSolution,
      HardwareGroup $hwGroup, string $jobConfigPath, User $submittedBy) {
    parent::__construct($submittedBy, $jobConfigPath);
    $this->referenceSolution = $referenceSolution;
    $this->hwGroup = $hwGroup;
    $this->failures = new ArrayCollection();
  }

  function isFailed(): bool {
    return $this->failures->count() > 0;
  }

  function isCorrect(): bool {
    return $this->hasEvaluation() && $this->evaluation->getTestResults()->forAll(function ($key, TestResult $testResult) {
      $diff = abs($testResult->getScore() - ER\TestResult::SCORE_MAX);
      return $diff < 0.001; // Safe float comparison
    });
  }

  public function getJobType(): string {
    return static::JOB_TYPE;
  }

}
