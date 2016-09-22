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
   * @ORM\Column(type="datetime")
   */
  protected $evaluatedAt;

  /**
   * @ORM\Column(type="string")
   */
  protected $hwgroup;

  /**
   * @ORM\Column(type="float")
   */
  protected $score;

  /**
   * @ORM\Column(type="text")
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

  public function __construct(ReferenceExerciseSolution $referenceSolution, string $hwgroup, EvaluationResults $results) {
    $this->referenceSolution = $referenceSolution;
    $this->evaluatedAt = new \DateTime;
    $this->hwgroup = $hwgroup;
    $this->score = 0;  // @todo: Somehow calculate the score.
    $this->resultYml = (string) $results;
  }
}