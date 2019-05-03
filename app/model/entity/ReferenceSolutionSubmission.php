<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
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
 * @method SubmissionFailure getFailure()
 * @method setFailure(SubmissionFailure $failure)
 */
class ReferenceSolutionSubmission extends Submission implements JsonSerializable, ES\IEvaluable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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
   * @ORM\OneToOne(targetEntity="SubmissionFailure", cascade={"persist", "remove"}, inversedBy="referenceSolutionSubmission", fetch="EAGER")
   * @var SubmissionFailure
   */
  protected $failure;

  public function jsonSerialize() {
    $evaluationData = null;
    if ($this->evaluation !== null) {
      $evaluationData = $this->evaluation->getData(true, true, true);
    }

    $failure = $this->getFailure();
    if ($failure && $failure->isConfigErrorFailure()) {
      $failures = [ $failure->toSimpleArray() ];  // BC - failures were originally many-to-one
    } else {
      $failures = [];
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
      "failures" => $failures,
    ];
  }

  public function __construct(ReferenceExerciseSolution $referenceSolution,
      ?HardwareGroup $hwGroup, string $jobConfigPath, User $submittedBy,
      bool $isDebug = false) {
    parent::__construct($submittedBy, $jobConfigPath, $isDebug);
    $this->referenceSolution = $referenceSolution;
    $this->hwGroup = $hwGroup;

    $referenceSolution->addSubmission($this);
  }

  function isFailed(): bool {
    return $this->failure !== null;
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

  public function getExercise(): ?IExercise {
    return $this->getReferenceSolution()->getExercise();
  }

  public function getAuthor(): ?User {
    return $this->getReferenceSolution()->getSolution()->getAuthor();
  }
}
