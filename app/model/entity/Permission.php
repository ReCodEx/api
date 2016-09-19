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
  public const ACTION_WILDCARD = "*";

  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="integer")
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="Role", inversedBy="permissions")
   */
  protected $role;

  public function getRoleId() { return $this->role->getId(); }

  /**
   * @ORM\ManyToOne(targetEntity="Resource", inversedBy="permissions")
   */
  protected $resource;

  public function getResourceId() { return $this->resource->getId(); }

  /**
   * @ORM\Column(type="string")
   */
  protected $action;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isAllowed;

  public function isAllowed() { return $this->isAllowed; }

  public function jsonSerialize() {
    return [
      "role" => $this->role,
      "resource" => $this->resource,
      "action" => $this->action,
      "isAllowed" => $this->isAllowed
    ];
  }

}
