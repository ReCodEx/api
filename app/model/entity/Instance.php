<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Instance implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  public function getId() { return $this->id; }

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  public function getName() { return $this->name; }

  /**
   * @ORM\Column(type="string")
   */
  protected $description;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isOpen;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isAllowed;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $updatedAt;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $admin;

  /**
   * @ORM\OneToMany(targetEntity="Licence", mappedBy="instance")
   */
  protected $licences;

  public function getLicences() {
    return $this->licences;
  }

  public function getValidLicences() {
    return $this->licences->filter(function ($licence) {
      return $licence->isValid === TRUE && $licence->validUntil > new \DateTime;
    });
  }

  public function hasValidLicence() {
    return $this->getValidLicences()->count() > 0;
  }

  /**
   * @ORM\OneToMany(targetEntity="Group", mappedBy="instance")
   */
  protected $groups;

  public function getTopLevelGroups() {
    return $this->groups;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->description,
      "hasValidLicence" => $this->hasValidLicence(),
      "isOpen" => $this->isOpen,
      "isAllowed" => $this->isAllowed,
      "createdAt" => $this->createdAt,
      "updatedAt" => $this->updatedAt,
      "admin" => $this->admin
    ];
  }

}
