<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method Group getRootGroup()
 * @method setAdmin(User $admin)
 */
class Instance implements JsonSerializable
{
  use MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="text", nullable=true)
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
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $deletedAt;

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
    return $this->licences->filter(function ($licence) {
      return $licence->isValid();
    });
  }

  public function hasValidLicence() {
    return $this->needsLicence === FALSE || $this->getValidLicences()->count() > 0;
  }

  /**
   * @ORM\OneToMany(targetEntity="User", mappedBy="instance")
   */
  protected $members;

  public function getMembers($search = NULL) {
    if ($search !== NULL && !empty($search)) {
      $filter = Criteria::create()
                  ->where(Criteria::expr()->contains("firstName", $search))
                  ->orWhere(Criteria::expr()->contains("lastName", $search));
      $members = $this->members->matching($filter);
      if ($members->count() > 0) {
        return $members;
      }

      // weaker filter - the strict one did not match anything
      $members = $this->members;
      foreach (explode(" ", $search) as $part) {
        // skip empty parts
        $part = trim($part);
        if (empty($part)) {
          continue;
        }

        $filter = Criteria::create()
                      ->orWhere(Criteria::expr()->contains("firstName", $part))
                      ->orWhere(Criteria::expr()->contains("lastName", $part));
        $members = $members->matching($filter);
      }

      return $members;
    } else {
      // no query is present
      return $this->members;
    }
  }

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

  public function getGroups() {
    return $this->groups->filter(function ($group) {
      return $group->getDeletedAt() === NULL;
    });
  }

  public function isAllowed() {
    return $this->isAllowed;
  }

  public function getData(User $user = NULL) {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->description,
      "hasValidLicence" => $this->hasValidLicence(),
      "isOpen" => $this->isOpen,
      "isAllowed" => $this->isAllowed,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "deletedAt" => $this->deletedAt ? $this->deletedAt->getTimestamp() : NULL,
      "admin" => $this->admin ? $this->admin->getId() : NULL,
      "rootGroupId" => $this->rootGroup !== NULL ? $this->rootGroup->getId() : NULL
    ];
  }

  public function jsonSerialize() {
    return $this->getData(NULL);
  }

  public function __construct(){
    $this->licences = new ArrayCollection();
    $this->groups = new ArrayCollection();
    $this->members = new ArrayCollection();
  }

  public static function createInstance(string $name, bool $isOpen, User $admin = NULL, string $description = NULL) {
    $instance = new Instance;
    $instance->name = $name;
    $instance->description = $description;
    $instance->isOpen = $isOpen;
    $instance->isAllowed = TRUE; //@todo - find out who should set this and how
    $instance->needsLicence = TRUE;
    $instance->rootGroup = NULL;
    $now = new \DateTime;
    $instance->createdAt = $now;
    $instance->updatedAt = $now;
    $instance->admin = $admin;

    // now create the root group for the instance
    $instance->rootGroup = new Group(
      $name,
      "",
      $description,
      $instance,
      $admin,
      NULL,
      FALSE,
      TRUE
    );

    return $instance;
  }

}
