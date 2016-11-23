<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 */
class LocalizedAssignment implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
    string $name,
    string $description,
    string $locale,
    $createdFrom = NULL
  ) {
    $this->name = $name;
    $this->description = $description;
    $this->locale = $locale;
    $this->assignments = new ArrayCollection;
    $this->localizedAssignment = $createdFrom;
    $this->createdAt = new DateTime;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * Created from.
   * @ORM\ManyToOne(targetEntity="LocalizedAssignment")
   */
  protected $localizedAssignment;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="string")
   */
  protected $locale;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\ManyToMany(targetEntity="Assignment", mappedBy="localizedAssignments")
   */
  protected $assignments;

  public function addAssignment(Assignment $assignment) {
    $this->assignments[] = $assignment;
    $assignment->addLocalizedAssignment($this);
    return $this;
  }

  public function removeAssignment(Assignment $assignment) {
    $this->assignments->removeElement($assignment);
    $assignment->removeLocalizedAssignment($this);
  }

  /**
   * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="localizedAssignments")
   */
  protected $exercises;

  public function addExercise(Exercise $exercise) {
    $this->exercises[] = $exercise;
    $exercise->addLocalizedAssignment($this);
    return $this;
  }

  public function removeExercise(Exercise $exercise) {
    $this->exercises->removeElement($exercise);
    $exercise->removeLocalizedAssignment($this);
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "locale" => $this->locale,
      "description" => $this->description,
      "createdAt" => $this->createdAt->getTimestamp(),
      "createdFrom" => $this->localizedAssignment ? $this->localizedAssignment->id : ""
    ];
  }
}
