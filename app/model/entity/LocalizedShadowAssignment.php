<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use JsonSerializable;

/**
 * @ORM\Entity
 * @method string getName()
 * @method string getAssignmentText()
 */
class LocalizedShadowAssignment extends LocalizedEntity implements JsonSerializable
{
  public function __construct(string $locale, string $name, string $assignmentText,
                              ?string $externalAssignmentLink = null, ?LocalizedShadowAssignment $createdFrom = null) {
    parent::__construct($locale);
    $this->assignmentText = $assignmentText;
    $this->name = $name;
    $this->createdFrom = $createdFrom;
    $this->externalAssignmentLink = $externalAssignmentLink;
  }

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * Text of the assignment (for students)
   * @ORM\Column(type="text")
   */
  protected $assignmentText;

  /**
   * A link to an external assignment for students
   * @ORM\Column(type="text", nullable=true)
   */
  protected $externalAssignmentLink;

  /**
   * @ORM\ManyToOne(targetEntity="LocalizedShadowAssignment")
   * @ORM\JoinColumn(onDelete="SET NULL")
   * @var LocalizedShadowAssignment
   */
  protected $createdFrom;

  public function equals(LocalizedEntity $other): bool {
    return $other instanceof LocalizedShadowAssignment
      && $this->assignmentText === $other->assignmentText
      && $this->externalAssignmentLink === $other->externalAssignmentLink
      && $this->name === $other->name;
  }

  public function setCreatedFrom(LocalizedEntity $entity) {
    if ($entity instanceof LocalizedShadowAssignment) {
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
      "link" => $this->externalAssignmentLink ?? "",
      "createdAt" => $this->createdAt->getTimestamp(),
      "createdFrom" => $this->createdFrom ? $this->createdFrom->getId() : ""
    ];
  }
}
