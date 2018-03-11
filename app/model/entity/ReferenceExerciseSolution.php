<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getDescription()
 * @method Solution getSolution()
 * @method Exercise getExercise()
 * @method Collection getSubmissions()
 */
class ReferenceExerciseSolution implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\OneToOne(targetEntity="Solution", cascade={"persist", "remove"})
   */
  protected $solution;

  /**
   * @ORM\OneToMany(targetEntity="ReferenceSolutionSubmission", mappedBy="referenceSolution", cascade={"remove"})
   */
  protected $submissions;

  /**
   * Add submission to solution entity.
   * @param ReferenceSolutionSubmission $submission
   */
  public function addSubmission(ReferenceSolutionSubmission $submission) {
    $this->submissions->add($submission);
  }

  public function getFiles() {
    return $this->solution->getFiles();
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "description" => $this->description,
      "solution" => $this->solution,
      "runtimeEnvironmentId" => $this->solution->getRuntimeEnvironment()->getId(),
      "submissions" => $this->submissions->map(
        function (ReferenceSolutionSubmission $evaluation) {
          return $evaluation->getId();
        }
      )->getValues()
    ];
  }

  public function __construct(Exercise $exercise, User $user, string $description, RuntimeEnvironment $runtime) {
    $this->exercise = $exercise;
    $this->description = $description;
    $this->solution = new Solution($user, $runtime);
    $this->submissions = new ArrayCollection;
  }

  public function getRuntimeEnvironment() {
    return $this->solution->getRuntimeEnvironment();
  }
}
