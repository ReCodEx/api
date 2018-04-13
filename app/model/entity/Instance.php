<?php

namespace App\Model\Entity;

use App\Helpers\Localizations;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method Group getRootGroup()
 * @method bool getIsAllowed()
 * @method setAdmin(User $admin)
 * @method setIsOpen(bool $isOpen)
 * @method Collection getLicences()
 */
class Instance implements JsonSerializable
{
  use MagicAccessors;
  use UpdateableEntity;
  use DeleteableEntity;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isOpen;

  /**
   * @return bool
   */
  public function isOpen(): bool {
    return $this->isOpen;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isAllowed;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $admin;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $needsLicence;

  /**
   * @ORM\ManyToOne(targetEntity="Group", cascade={"persist"})
   * @var Group
   */
  protected $rootGroup;

  /**
   * @var ArrayCollection
   * @ORM\OneToMany(targetEntity="Licence", mappedBy="instance")
   */
  protected $licences;

  public function addLicence(Licence $licence)
  {
    $this->licences->add($licence);
  }

  public function getValidLicences() {
    return $this->licences->filter(function (Licence $licence) {
      return $licence->isValid();
    });
  }

  public function hasValidLicence() {
    return $this->needsLicence === false || $this->getValidLicences()->count() > 0;
  }

  /**
   * @ORM\OneToMany(targetEntity="User", mappedBy="instance")
   */
  protected $members;

  public function addMember(User $user) {
    $this->members->add($user);
  }

  /**
   * @ORM\OneToMany(targetEntity="Group", mappedBy="instance", cascade={"persist"})
   */
  protected $groups;

  public function addGroup(Group $group) {
    $this->groups->add($group);
  }

  public function getGroups(): Collection {
    return $this->groups->filter(function (Group $group) {
      return $group->getDeletedAt() === null;
    });
  }

  public function isAllowed() {
    return $this->isAllowed;
  }

  public function jsonSerialize() {
    /** @var LocalizedGroup $localizedRootGroup */
    $localizedRootGroup = Localizations::getPrimaryLocalization($this->rootGroup->getLocalizedTexts());

    return [
      "id" => $this->id,
      "name" => $localizedRootGroup ? $localizedRootGroup->getName() : "",
      "description" => $localizedRootGroup ? $localizedRootGroup->getDescription() : "",
      "hasValidLicence" => $this->hasValidLicence(),
      "isOpen" => $this->isOpen,
      "isAllowed" => $this->isAllowed,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "deletedAt" => $this->deletedAt ? $this->deletedAt->getTimestamp() : null,
      "admin" => $this->admin ? $this->admin->getId() : null,
      "rootGroupId" => $this->rootGroup !== null ? $this->rootGroup->getId() : null
    ];
  }

  public function __construct(){
    $this->licences = new ArrayCollection();
    $this->groups = new ArrayCollection();
    $this->members = new ArrayCollection();
  }

  public function getName() {
    /** @var LocalizedGroup $localizedRootGroup */
    $localizedRootGroup = Localizations::getPrimaryLocalization($this->rootGroup->getLocalizedTexts());
    return $localizedRootGroup->getName();
  }

  public static function createInstance(array $localizedTexts, bool $isOpen, User $admin = null) {
    $instance = new Instance;
    $instance->isOpen = $isOpen;
    $instance->isAllowed = true; //@todo - find out who should set this and how
    $instance->needsLicence = true;
    $now = new \DateTime;
    $instance->createdAt = $now;
    $instance->updatedAt = $now;
    $instance->admin = $admin;

    // now create the root group for the instance
    $instance->rootGroup = new Group(
      "",
      $instance,
      $admin,
      null,
      false,
      true
    );

    /** @var LocalizedGroup $text */
    foreach ($localizedTexts as $text) {
      $instance->rootGroup->addLocalizedText($text);
    }

    return $instance;
  }

}
