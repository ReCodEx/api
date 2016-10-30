<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 */
class HardwareGroupAvailabilityLog implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="HardwareGroup", inversedBy="availabilityLog")
   */
  protected $hardwareGroup;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isAvailable;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $loggedAt;

  /**
   * @ORM\Column(type="string")
   */
  protected $description;

  public function __construct(
    HardwareGroup $hwGroup,
    bool $isAvailable,
    string $description,
    DateTime $when = NULL
  ) {
    $this->hardwareGroup = $hwGroup;
    $this->isAvailable = $isAvailable;
    $this->desciption = $description;
    $this->loggedAt = $when === NULL ? new DateTime : $when;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "hardwareGroupId" => $this->hardwareGroup->getId(),
      "isAvailable" => $this->isAvailable,
      "loggedAt" => $this->loggedAt,
      "description" => $this->description
    ];
  }

}
