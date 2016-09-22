<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 */
class ReferenceSolutionEvaluation implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="ReferenceExerciseSolution")
   * @ORM\JoinColumn(name="reference_exercise_solution_id", referencedColumnName="id")
   */
  protected $referenceSolution;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $evaluatedAt;

  /**
   * @ORM\Column(type="string")
   */
  protected $hwgroup;

  /**
   * @ORM\Column(type="float", nullable=true)
   */
  protected $score;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $resultsUrl;

  /**
   * @ORM\Column(type="text", nullable=true)
   */
  protected $resultYml;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "evaluatedAt" => $this->evaluatedAt->getTimestamp(),
      "hwgroup" => $this->hwgroup,
      "resultYml" => $this->resultYml
    ];
  }

  public function __construct(ReferenceExerciseSolution $referenceSolution, string $hwgroup) {
    $this->referenceSolution = $referenceSolution;
    $this->hwgroup = $hwgroup;
  }

  public function saveResults(EvaluationResults $results) {
    $this->evaluatedAt = new \DateTime;
    $this->score = 0;  // @todo: Somehow calculate the score.
    $this->resultYml = (string) $results;
  }
}
