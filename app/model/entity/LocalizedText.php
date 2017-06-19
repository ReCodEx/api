<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * @method string getId()
 */
class LocalizedText implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
    string $text,
    string $locale,
    $createdFrom = NULL
  ) {
    $this->text = $text;
    $this->locale = $locale;
    $this->assignments = new ArrayCollection;
    $this->exercises = new ArrayCollection;
    $this->createdFrom = $createdFrom;
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
   * @ORM\ManyToOne(targetEntity="LocalizedText")
   * @var LocalizedText
   */
  protected $createdFrom;

  /**
   * @ORM\Column(type="string")
   */
  protected $locale;

  /**
   * @ORM\Column(type="text")
   */
  protected $text;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\ManyToMany(targetEntity="Assignment", mappedBy="localizedTexts")
   */
  protected $assignments;

  public function addAssignment(Assignment $assignment) {
    $this->assignments[] = $assignment;
    $assignment->addLocalizedText($this);
    return $this;
  }

  public function removeAssignment(Assignment $assignment) {
    $this->assignments->removeElement($assignment);
    $assignment->removeLocalizedText($this);
  }

  /**
   * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="localizedTexts")
   */
  protected $exercises;

  public function addExercise(Exercise $exercise) {
    $this->exercises[] = $exercise;
    $exercise->addLocalizedText($this);
    return $this;
  }

  public function removeExercise(Exercise $exercise) {
    $this->exercises->removeElement($exercise);
    $exercise->removeLocalizedText($this);
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "locale" => $this->locale,
      "text" => $this->text,
      "createdAt" => $this->createdAt->getTimestamp(),
      "createdFrom" => $this->createdFrom ? $this->createdFrom->getId() : ""
    ];
  }
}
