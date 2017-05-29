<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;

/**
 * @ORM\Entity
 * @method string getId()
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
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\OneToMany(targetEntity="HardwareGroupAvailabilityLog", mappedBy="hardwareGroup")
   * @ORM\OrderBy({ "loggedAt" = "DESC" })
   */
  protected $availabilityLog;

  /**
   * Find out whether the hardware group is available now or was available at a given time.
   * @param DateTime $when Explicit time
   * @return bool
   */
  public function isAvailable(DateTime $when = NULL): bool {
    if ($when === NULL) {
      $when = new DateTime;
    }

    $criteria = Criteria::create()->where(Criteria::expr()->lte("loggedAt", $when));
    $latestLog = $this->availabilityLog->matching($criteria)->first();
    if (!$latestLog) {
      return FALSE;
    }

    return $latestLog->isAvailable();
  }

  public function __construct(
    string $id,
    string $description
  ) {
    $this->id = $id;
    $this->description = $description;
    $this->availabilityLog = new ArrayCollection;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "description" => $this->description,
      "isAvailable" => $this->isAvailable()
    ];
  }

}
