<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 *
 * @method string getNote()
 * @method setNote(string $note)
 * @method DateTime getValidUntil()
 * @method setValidUntil(DateTime $validUntil)
 * @method setIsValid(bool $isValid)
 */
class Licence implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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
   * A licence can be manually marked as invalid by the admins.
   * @ORM\Column(type="boolean")
   */
  protected $isValid;

  /**
   * The very last date on which this licence is valid (unless invalidated manually)
   * @ORM\Column(type="datetime")
   * @var DateTime
   */
  protected $validUntil;

  /**
   * Checks if the licence is valid at a given moment - by default right now.
   * @param DateTime $when When the licence should have been valid.
   * @return bool
   */
  public function isValid(\DateTime $when = null) {
    if ($when === null) {
      $when = new \DateTime;
    }
    return $this->isValid && $this->validUntil >= $when;
  }

  /**
   * Internal note for the licence.
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

  public static function createLicence(string $note, \DateTime $validUntil, Instance $instance, bool $isValid = TRUE) {
    $licence = new Licence();
    $licence->note = $note;
    $licence->validUntil = $validUntil;
    $licence->isValid = $isValid;
    $licence->instance = $instance;
    $instance->addLicence($licence);
    return $licence;
  }

}
