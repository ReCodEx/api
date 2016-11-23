<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * @method Solution getSolution()
 */
class ReferenceExerciseSolution implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise", inversedBy="referenceSolutions")
   */
  protected $exercise;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $uploadedAt;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\OneToOne(targetEntity="Solution", cascade={"persist"})
   */
  protected $solution;

  /**
   * @ORM\OneToMany(targetEntity="ReferenceSolutionEvaluation", mappedBy="referenceSolution")
   */
  protected $evaluations;

  public function getFiles() {
    return $this->solution->getFiles();
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "uploadedAt" => $this->uploadedAt->getTimestamp(),
      "description" => $this->description,
      "solution" => $this->solution,
      "evaluations" => $this->evaluations->map(
        function ($evaluation) {
          return $evaluation->getId();
        }
      )->getValues()
    ];
  }

  public function __construct(Exercise $exercise, User $user, string $description, SolutionRuntimeConfig $runtime) {
    $this->exercise = $exercise;
    $this->uploadedAt = new \DateTime;
    $this->description = $description;
    $this->solution = new Solution($user, $runtime);
    $this->evaluations = new ArrayCollection;
  }
}
