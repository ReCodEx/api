<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 * @method string getId()
 * @method string getName()
 * @method string getDescription()
 */
class HardwareGroup implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="string")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;


  public function __construct(
    string $id,
    string $description,
    string $name = ""
  ) {
    $this->id = $id;
    $this->description = $description;
    $this->name = $name;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->description
    ];
  }

}
