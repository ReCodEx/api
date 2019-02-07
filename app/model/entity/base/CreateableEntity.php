<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait CreateableEntity {

  /**
   * @ORM\Column(type="datetime")
   * @var DateTime
   */
  protected $createdAt;

  public function getCreatedAt(): DateTime {
    return $this->createdAt;
  }

  public function createdNow() {
    $this->createdAt = new DateTime();
  }
}
