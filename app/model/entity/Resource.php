<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Resource implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="string")
   */
  protected $id;

  /**
   * @ORM\OneToMany(targetEntity="Permission", mappedBy="resource")
   */
  protected $permissions;

  public function jsonSerialize() {
    return $this->id;
  }

}
