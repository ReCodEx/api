<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Role implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="string")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="Role", inversedBy="childRoles")
   */
  protected $parentRole;

  /**
   * @ORM\OneToMany(targetEntity="Role", mappedBy="parentRole")
   */
  protected $childRoles;

  public function jsonSerialize() {
    return $this->id;
  }

}
