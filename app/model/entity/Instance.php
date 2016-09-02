<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
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
   * @ORM\Column(type="string", nullable=true)
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
    $filter = Criteria::create()->where(Criteria::expr()->eq("parentGroup", NULL));
    return $this->groups->matching($filter);
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->description,
      "hasValidLicence" => $this->hasValidLicence(),
      "isOpen" => $this->isOpen,
      "isAllowed" => $this->isAllowed,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "admin" => $this->admin,
      "topLevelGroups" => $this->getTopLevelGroups()->map(function($group) { return $group->getId(); })->toArray()
    ];
  }

  public static function createInstance(string $name, bool $isOpen, User $admin, string $description = NULL) {
    $instance = new Instance;
    $instance->name = $name;
    $instance->description = $description;
    $instance->isOpen = $isOpen;
    $instance->isAllowed = TRUE; //@todo - find out who should set this and how
    $now = new \DateTime;
    $instance->createdAt = $now;
    $instance->updatedAt = $now;
    $instance->admin = $admin;
    $instance->licences = new ArrayCollection;
    $instance->groups = new ArrayCollection;
    return $instance;
  }

}
