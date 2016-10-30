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

  const STUDENT = "student";
  const SUPERVISOR = "supervisor";
  const SUPERADMIN = "superadmin";

  /**
   * @ORM\Id
   * @ORM\Column(type="string")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="Role", inversedBy="childRoles")
   */
  protected $parentRole;

  public function __construct()
  {
    $this->childRoles = new ArrayCollection();
  }

  public function getParentRoleId() {
    return $this->parentRole === NULL ? NULL : $this->parentRole->getId();
  }

  /**
   * @ORM\OneToMany(targetEntity="Role", mappedBy="parentRole")
   */
  protected $childRoles;

  /**
   * @ORM\OneToMany(targetEntity="Permission", mappedBy="role")
   */
  protected $permissions;

  public function jsonSerialize() {
    return $this->id;
  }

  public function hasLimitedRights() {
    return $this->id !== "superadmin";
  }

  protected function isInRole($role) {
    return $this->id === $role;
  }

  public function isStudent() {
    return $this->isInRole(self::STUDENT);
  }

  public function isSupervisor() {
    return $this->isInRole(self::SUPERVISOR);
  }

  public function isSuperadmin() {
    return $this->isInRole(self::SUPERADMIN);
  }

}
