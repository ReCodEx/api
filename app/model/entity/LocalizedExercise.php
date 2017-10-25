<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class LocalizedExercise extends LocalizedEntity implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
    string $text,
    string $locale,
    ?string $shortText = NULL,
    $createdFrom = NULL
  ) {
    parent::__construct($locale);
    $this->text = $text;
    $this->shortText = $shortText;
    $this->createdFrom = $createdFrom;
  }

  /**
   * @ORM\Column(type="string", nullable=TRUE)
   */
  protected $shortText;

  /**
   * @ORM\Column(type="text")
   */
  protected $text;

  /**
   * @ORM\ManyToOne(targetEntity="LocalizedExercise")
   * @var LocalizedExercise
   */
  protected $createdFrom;

  public function equals(LocalizedEntity $other): bool {
    return $other instanceof LocalizedExercise && $this->text === $other->text && $this->shortText === $other->shortText;
  }

  public function setCreatedFrom(LocalizedEntity $entity) {
    $this->createdFrom = $entity;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "locale" => $this->locale,
      "shortText" => $this->shortText,
      "text" => $this->text,
      "createdAt" => $this->createdAt->getTimestamp(),
      "createdFrom" => $this->createdFrom ? $this->createdFrom->getId() : ""
    ];
  }
}
