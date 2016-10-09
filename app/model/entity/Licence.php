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
   * @ORM\Column(type="boolean")
   */
  protected $isValid;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $validUntil;

  /**
   * Checks if the licence is valid at a given moment - by default right now.
   * @param DateTime $when When the licence should have been valid. 
   * @return bool
   */
  public function isValid(\DateTime $when = NULL) {
    if ($when === NULL) {
      $when = new \DateTime;
    }
    return $this->isValid && $this->validUntil >= $when;
  }

  /**
   * @ORM\Column(type="string")
   */
  protected $note;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "note" => $this->note,
      "isValid" => $this->isValid,
      "validUntil" => $this->validUntil->getTimestamp()
    ];
  }

  public static function createLicence(string $note, \DateTime $validUntil, Instance $instance) {
    $licence = new Licence();
    $licence->note = $note;
    $licence->validUntil = $validUntil;
    $licence->isValid = TRUE; //@todo ask Simon the meaning of this
    $licence->instance = $instance;
    $instance->licences->add($licence);
    return $licence;
  }

}
