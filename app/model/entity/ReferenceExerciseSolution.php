<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method string getDescription()
 * @method Solution getSolution()
 * @method Exercise getExercise()
 * @method Collection getEvaluations()
 * @method \DateTime getDeletedAt()
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
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $deletedAt;

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
      "runtimeEnvironmentId" => $this->solution->getRuntimeEnvironment()->getId(),
      "evaluations" => $this->evaluations->map(
        function (ReferenceSolutionEvaluation $evaluation) {
          return $evaluation->getId();
        }
      )->getValues()
    ];
  }

  public function __construct(Exercise $exercise, User $user, string $description, RuntimeEnvironment $runtime) {
    $this->exercise = $exercise;
    $this->uploadedAt = new \DateTime;
    $this->description = $description;
    $this->solution = new Solution($user, $runtime);
    $this->evaluations = new ArrayCollection;
  }

  public function getRuntimeEnvironment() {
    return $this->solution->getRuntimeEnvironment();
  }
}
