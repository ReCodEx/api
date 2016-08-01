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

  public function getId() { return $this->id; }

  /**
   * @ORM\ManyToOne(targetEntity="Role", inversedBy="childRoles")
   */
  protected $parentRole;

  public funtion getParentRoleId() { $this->parentRole->getId(); }

  /**
   * @ORM\OneToMany(targetEntity="Role", mappedBy="parentRole")
   */
  protected $childRoles;

  /**
   * @ORM\ManyToOne(targetEntity="Permission", inversedBy="role")
   */
  protected $permissions;

  public function jsonSerialize() {
    return $this->id;
  }

  public function hasLimitedRights() {
    return $this->id === 'student';
  }

}
