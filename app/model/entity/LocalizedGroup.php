<?php
namespace App\Model\Entity;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 * @method string getName()
 * @method string getDescription()
 */
class LocalizedGroup extends LocalizedEntity implements JsonSerializable {
  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\ManyToOne(targetEntity="LocalizedGroup")
   * @ORM\JoinColumn(onDelete="SET NULL")
   */
  protected $createdFrom;

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="localizedTexts")
   * @ORM\JoinColumn(nullable=true)
   */
  protected $group;

  public function __construct($locale, string $name, string $description, ?LocalizedGroup $createdFrom = NULL) {
    parent::__construct($locale);
    $this->name = $name;
    $this->description = $description;
    $this->createdFrom = $createdFrom;
  }


  public function equals(LocalizedEntity $entity): bool {
    return $entity instanceof LocalizedGroup
      && $this->name === $entity->getName()
      && $this->description === $entity->getDescription();
  }

  public function setCreatedFrom(LocalizedEntity $entity) {
    if ($entity instanceof LocalizedGroup) {
      $this->createdFrom = $entity;
    }
  }

  public function setGroup(?Group $group = null) {
    $this->group = $group;

    if ($group !== null && !$group->getLocalizedTexts()->contains($this)) {
      $group->getLocalizedTexts()->add($this);
    }
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "locale" => $this->locale,
      "name" => $this->name,
      "description" => $this->description,
      "createdAt" => $this->createdAt->getTimestamp(),
      "createdFrom" => $this->createdFrom ? $this->createdFrom->getId() : ""
    ];
  }
}
