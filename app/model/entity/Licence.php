<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Licence implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="Instance", inversedBy="licences")
   */
  protected $instance;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isValid;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $validUntil;

  /**
   * @ORM\Column(type="string")
   */
  protected $note;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "isValid" => $this->isValid,
      "validUntil" => $this->validUntil
    ];
  }

}
