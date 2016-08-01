<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Permission implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="string")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="Role")
   */
  protected $role;

  public function getRoleId() { return $this->role->getId(); }

  /**
   * @ORM\ManyToOne(targetEntity="Resource")
   */
  protected $resource;

  public function getResourceId() { return $this->resource->getId(); }

  /**
   * @ORM\Column(type="bool")
   */
  protected $isAllowed;

  public function isAllowed() { return $this->isAllowed; }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "role" => $this->role,
      "resource" => $this->resource,
      "isAllowed" => $this->isAllowed
    ];
  }

}
