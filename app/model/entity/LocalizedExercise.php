<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;
use JsonSerializable;

/**
 * @ORM\Entity
 * @method string getName()
 * @method string getDescription()
 * @method string getAssignmentText()
 */
class LocalizedExercise extends LocalizedEntity implements JsonSerializable
{
  public function __construct(
    string $locale,
    string $name,
    string $assignmentText,
    string $description = "",
    LocalizedExercise $createdFrom = null
  ) {
    parent::__construct($locale);
    $this->assignmentText = $assignmentText;
    $this->name = $name;
    $this->description = $description;
    $this->createdFrom = $createdFrom;
  }

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * A short description of the exercise (for teachers)
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * Text of the assignment (for students)
   * @ORM\Column(type="text")
   */
  protected $assignmentText;

  /**
   * @ORM\ManyToOne(targetEntity="LocalizedExercise")
   * @ORM\JoinColumn(onDelete="SET NULL")
   * @var LocalizedExercise
   */
  protected $createdFrom;

  public function equals(LocalizedEntity $other): bool {
    return $other instanceof LocalizedExercise
      && $this->description === $other->description
      && $this->assignmentText === $other->assignmentText
      && $this->name === $other->name;
  }

  public function setCreatedFrom(LocalizedEntity $entity) {
    if ($entity instanceof LocalizedExercise) {
      $this->createdFrom = $entity;
    } else {
      throw new InvalidArgumentException("Wrong type of entity supplied");
    }
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "locale" => $this->locale,
      "name" => $this->name,
      "text" => $this->assignmentText,
      "description" => $this->description,
      "createdAt" => $this->createdAt->getTimestamp(),
      "createdFrom" => $this->createdFrom ? $this->createdFrom->getId() : ""
    ];
  }
}
