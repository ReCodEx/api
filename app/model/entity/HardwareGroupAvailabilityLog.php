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
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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

  public function isAvailable() {
    return $this->isAvailable;
  }

  /**
   * @ORM\Column(type="datetime")
   */
  protected $loggedAt;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  public function __construct(
    HardwareGroup $hwGroup,
    bool $isAvailable,
    string $description,
    DateTime $when = null
  ) {
    $this->hardwareGroup = $hwGroup;
    $this->isAvailable = $isAvailable;
    $this->description = $description;
    $this->loggedAt = $when === null ? new DateTime : $when;
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
