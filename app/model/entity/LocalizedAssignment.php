<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 */
class LocalizedAssignment implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  private function __construct(
    string $name,
    string $description,
    string $locale
  ) {
    $this->name = $name;
    $this->description = $description;
    $this->locale = $locale;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

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

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "locale" => $this->locale,
      "description" => $this->getDescription(),
    ];
  }
}
