<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class HardwareGroup implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="string")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $description;

  public function __construct(
    string $id,
    string $description
  ) {
    $this->id = $id;
    $this->desciption = $description;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "description" => $this->description
    ];
  }

}
