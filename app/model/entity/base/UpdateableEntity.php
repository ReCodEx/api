<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;


trait UpdateableEntity {

  /**
   * @ORM\Column(type="datetime")
   * @var DateTime
   */
  protected $updatedAt;

  public function getUpdatedAt(): DateTime {
    return $this->updatedAt;
  }

  public function updatedNow() {
    $this->updatedAt = new DateTime;
  }

}
