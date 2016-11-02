<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;
use App\Helpers\EvaluationStatus as ES;

/**
 * @ORM\Entity
 */
class ReferenceSolutionEvaluation implements JsonSerializable, ES\IEvaluable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  const JOB_TYPE = "recodex-reference-solution";

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="ReferenceExerciseSolution", inversedBy="evaluations")
   */
  protected $referenceSolution;

  /**
   * @ORM\Column(type="string")
   */
  protected $hwGroup;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $resultsUrl;

  public function canBeEvaluated(): bool {
    return $this->resultsUrl !== NULL;
  }

  /**
    * @ORM\OneToOne(targetEntity="SolutionEvaluation", cascade={"persist", "remove"})
    */
  protected $evaluation;

  public function hasEvaluation(): bool {
    return $this->evaluation !== NULL;
  }

  public function getEvaluation(): SolutionEvaluation {
    return $this->evaluation;
  }

  public function setEvaluation(SolutionEvaluation $evaluation) {
    $this->evaluation = $evaluation;
    $this->solution->setEvaluated(TRUE);
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "evaluationStatus" => ES\EvaluationStatus::getStatus($this),
      "evaluation" => $this->evaluation
    ];
  }

  public function __construct(ReferenceExerciseSolution $referenceSolution, string $hwGroup) {
    $this->referenceSolution = $referenceSolution;
    $this->hwGroup = $hwGroup;
  }

}
