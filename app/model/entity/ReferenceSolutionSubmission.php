<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;
use App\Helpers\EvaluationStatus as ES;
use App\Helpers\EvaluationResults as ER;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getResultsUrl()
 * @method ReferenceExerciseSolution getReferenceSolution()
 * @method string setResultsUrl(string $url)
 * @method string getJobConfigPath()
 */
class ReferenceSolutionSubmission implements JsonSerializable, ES\IEvaluable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  const JOB_TYPE = "reference";

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

  /**
   * @ORM\ManyToOne(targetEntity="ReferenceExerciseSolution", inversedBy="submissions")
   */
  protected $referenceSolution;

  /**
   * @ORM\ManyToOne(targetEntity="HardwareGroup")
   */
  protected $hwGroup;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $resultsUrl;

  /**
   * @ORM\Column(type="string")
   */
  protected $jobConfigPath;

  /**
   * @var Collection
   * @ORM\OneToMany(targetEntity="SubmissionFailure", mappedBy="referenceSolutionSubmission")
   */
  protected $failures;

  public function canBeEvaluated(): bool {
    return $this->resultsUrl !== NULL;
  }

  /**
   * @ORM\OneToOne(targetEntity="SolutionEvaluation", inversedBy="referenceSolutionSubmission", cascade={"persist", "remove"})
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
    $this->referenceSolution = $referenceSolution;
    $this->hwGroup = $hwGroup;
    $this->jobConfigPath = $jobConfigPath;
    $this->failures = new ArrayCollection();
    $this->submittedAt = new DateTime;
    $this->submittedBy = $submittedBy;
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

}
