<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;


trait VersionableEntity {

  /**
   * @ORM\Column(type="integer")
   */
  protected $version = 1;

  public function getVersion(): int {
    return $this->version;
  }

  /**
   * Increment version number.
   */
  public function incrementVersion() {
    $this->version++;
  }
}
