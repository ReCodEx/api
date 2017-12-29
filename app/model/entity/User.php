<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use forxer\Gravatar\Gravatar;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method string getEmail()
 * @method string getRole()
 * @method string getAvatarUrl()
 * @method Instance getInstance()
 * @method Collection getExercises()
 * @method UserSettings getSettings()
 * @method setUsername(string $username)
 * @method setFirstName(string $firstName)
 * @method getFirstName()
 * @method setLastName(string $lastName)
 * @method getLastName()
 * @method setDegreesBeforeName(string $degrees)
 * @method setDegreesAfterName(string $degrees)
 * @method setRole(string $role)
 * @method Collection getLogins()
 */
class User
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;
  use DeleteableEntity;

  public const STUDENT_ROLE = "student";
  public const SUPERVISOR_ROLE = "supervisor";
  public const SUPERADMIN_ROLE = "superadmin";

  public function __construct(
    string $email,
    string $firstName,
    string $lastName,
    string $degreesBeforeName,
    string $degreesAfterName,
    ?string $role,
    Instance $instance,
    bool $instanceAdmin = FALSE
  ) {
    $this->firstName = $firstName;
    $this->lastName = $lastName;
    $this->degreesBeforeName = $degreesBeforeName;
    $this->degreesAfterName = $degreesAfterName;
    $this->setEmail($email);
    $this->isVerified = FALSE;
    $this->isAllowed = TRUE;
    $this->memberships = new ArrayCollection;
    $this->exercises = new ArrayCollection;
    $this->createdAt = new DateTime();
    $this->deletedAt = NULL;
    $this->instance = $instance;
    $instance->addMember($this);
    $this->settings = new UserSettings(TRUE, FALSE, "en");
    $this->logins = new ArrayCollection();

    if (empty($role)) {
      $this->role = self::STUDENT_ROLE;
    } else {
      $this->role = $role;
    }

    if ($instanceAdmin) {
      $instance->setAdmin($this);
    }
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $degreesBeforeName;

  /**
   * @ORM\Column(type="string")
   */
  protected $firstName;

  /**
   * @ORM\Column(type="string")
   */
  protected $lastName;

  public function getName() {
    return trim("{$this->degreesBeforeName} {$this->firstName} {$this->lastName} {$this->degreesAfterName}");
  }

  /**
   * @ORM\Column(type="string")
   */
  protected $degreesAfterName;

  /**
   * @ORM\Column(type="string")
   */
  protected $email;

  public function setEmail($email) {
    $this->email = $email;
    $this->avatarUrl = Gravatar::image($email, 200, "retro", "g", "png", true, false);
  }

  /**
   * @ORM\Column(type="string")
   */
  protected $avatarUrl;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isVerified;

  public function isVerified() { return $this->isVerified; }

  public function setVerified($verified = TRUE) {
    $this->isVerified = $verified;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isAllowed;

  public function isAllowed() { return $this->isAllowed; }

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\ManyToOne(targetEntity="Instance", inversedBy="members")
   */
  protected $instance;

  public function belongsTo(Instance $instance) {
    return $this->instance->getId() === $instance->getId();
  }

   /**
    * @ORM\OneToOne(targetEntity="UserSettings", cascade={"persist"})
    */
   protected $settings;

  /**
   * @ORM\OneToMany(targetEntity="GroupMembership", mappedBy="user", cascade={"all"})
   */
  protected $memberships;

  protected function findMembership(Group $group, string $type) {
    $filter = Criteria::create()
            ->where(Criteria::expr()->eq("group", $group))
            ->andWhere(Criteria::expr()->eq("type", $type));
    $filtered = $this->memberships->matching($filter);
    if ($filtered->isEmpty()) {
      return NULL;
    }

    if ($filtered->count() > 1) {
      // @todo: handle this situation, when this user is double member of the same group
    }

    return $filtered->first();
  }

  public function findMembershipAsStudent(Group $group) {
    return $this->findMembership($group, GroupMembership::TYPE_STUDENT);
  }

  public function findMembershipAsSupervisor(Group $group) {
    return $this->findMembership($group, GroupMembership::TYPE_SUPERVISOR);
  }

  protected function getMemberships() {
    return $this->memberships->filter(function (GroupMembership $membership) {
      return $membership->getGroup()->getDeletedAt() === NULL;
    });
  }

  /**
   * Returns array with all groups in which this user has given type.
   * @param string $type
   * @return ArrayCollection
   */
  protected function findGroupMemberships($type) {
    $filter = Criteria::create()
            ->where(Criteria::expr()->eq("type", $type));
    return $this->getMemberships()->matching($filter)->getValues();
  }

  public function findGroupMembershipsAsSupervisor() {
    return $this->findGroupMemberships(GroupMembership::TYPE_SUPERVISOR);
  }

  public function findGroupMembershipsAsStudent() {
    return $this->findGroupMemberships(GroupMembership::TYPE_STUDENT);
  }

  protected function addMembership(Group $group, string $type) {
    $membership = new GroupMembership($group, $this, $type, GroupMembership::STATUS_ACTIVE);
    $this->memberships->add($membership);
    $group->addMembership($membership);
  }

  protected function makeMemberOf(Group $group, string $type) {
    $membership = $this->findMembership($group, $type);
    if ($membership === NULL) {
      $this->addMembership($group, $type);
    } else {
      $membership->setType($type);
      $membership->setStatus(GroupMembership::STATUS_ACTIVE);
    }
  }

  public function getGroups(string $type = NULL) {
    $result = $this->getMemberships();

    if ($type !== NULL) {
      $filter = Criteria::create()->where(Criteria::expr()->eq("type", $type));
      $result = $result->matching($filter);
    }

    return $result->map(
      function (GroupMembership $membership) {
        return $membership->getGroup();
      }
    );
  }

  public function getGroupsAsStudent() {
    return $this->getGroups(GroupMembership::TYPE_STUDENT);
  }

  public function makeStudentOf(Group $group) {
    $this->makeMemberOf($group, GroupMembership::TYPE_STUDENT);
  }

  public function getGroupsAsSupervisor() {
    return $this->getGroups(GroupMembership::TYPE_SUPERVISOR);
  }

  public function makeSupervisorOf(Group $group) {
    $this->makeMemberOf($group, GroupMembership::TYPE_SUPERVISOR);
  }

  /**
   * @ORM\OneToMany(targetEntity="Exercise", mappedBy="author")
   */
  protected $exercises;

  /**
   * @ORM\Column(type="string")
   */
  protected $role;

  /**
   * @ORM\OneToMany(targetEntity="Login", mappedBy="user")
   */
  protected $logins;


  /**
   * @return array
   */
  public function getNameParts(): array {
    return [
      "degreesBeforeName" => $this->degreesBeforeName,
      "firstName" => $this->firstName,
      "lastName" => $this->lastName,
      "degreesAfterName" => $this->degreesAfterName,
    ];
  }
}
