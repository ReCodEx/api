<?php

namespace App\Model\Entity;

use App\Security\Roles;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use forxer\Gravatar\Gravatar;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method string getEmail()
 * @method string getRole()
 * @method string getAvatarUrl()
 * @method ArrayCollection getInstances()
 * @method Collection getExercises()
 * @method UserSettings getSettings()
 * @method setUsername(string $username)
 * @method setFirstName(string $firstName)
 * @method getFirstName()
 * @method setLastName(string $lastName)
 * @method getLastName()
 * @method setEmail(string $email)
 * @method setDegreesBeforeName(string $degrees)
 * @method setDegreesAfterName(string $degrees)
 * @method setRole(string $role)
 * @method Collection getLogins()
 * @method Collection getExternalLogins()
 * @method setTokenValidityThreshold(DateTime $threshold)
 * @method DateTime|null getTokenValidityThreshold()
 */
class User
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;
  use DeleteableEntity;

  public function __construct(
    string $email,
    string $firstName,
    string $lastName,
    string $degreesBeforeName,
    string $degreesAfterName,
    ?string $role,
    Instance $instance,
    bool $instanceAdmin = false
  ) {
    $this->firstName = $firstName;
    $this->lastName = $lastName;
    $this->degreesBeforeName = $degreesBeforeName;
    $this->degreesAfterName = $degreesAfterName;
    $this->email = $email;
    $this->isVerified = false;
    $this->isAllowed = true;
    $this->memberships = new ArrayCollection;
    $this->exercises = new ArrayCollection;
    $this->createdAt = new DateTime();
    $this->deletedAt = null;
    $this->instances = new ArrayCollection([$instance]);
    $instance->addMember($this);
    $this->settings = new UserSettings(true, false, "en");
    $this->logins = new ArrayCollection();
    $this->externalLogins = new ArrayCollection();
    $this->avatarUrl = null;

    if (empty($role)) {
      $this->role = Roles::STUDENT_ROLE;
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

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $avatarUrl;

  /**
   * If true, then set gravatar image based on user email.
   * @param bool $useGravatar
   */
  public function setGravatar(bool $useGravatar = true) {
    $this->avatarUrl = !$useGravatar ? null :
      Gravatar::image($this->email, 200, "retro", "g", "png", true, false);
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isVerified;

  public function isVerified() { return $this->isVerified; }

  public function setVerified($verified = true) {
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
   * @ORM\ManyToMany(targetEntity="Instance", inversedBy="members")
   */
  protected $instances;

  public function belongsTo(Instance $instance) {
    return $this->instances->contains($instance);
  }

  public function getInstancesIds() {
    return $this->instances->map(function (Instance $instance) {
      return $instance->getId();
    })->getValues();
  }

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $tokenValidityThreshold;

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
      return null;
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
      return $membership->getGroup()->getDeletedAt() === null;
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
    if ($membership === null) {
      $this->addMembership($group, $type);
    } else {
      $membership->setType($type);
      $membership->setStatus(GroupMembership::STATUS_ACTIVE);
    }
  }

  public function getGroups(string $type = null) {
    $result = $this->getMemberships();

    if ($type !== null) {
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
   * @ORM\OneToMany(targetEntity="ExternalLogin", mappedBy="user", cascade={"all"})
   */
  protected $externalLogins;

  /**
   * @ORM\OneToMany(targetEntity="Login", mappedBy="user", cascade={"all"})
   */
  protected $logins;

  /**
   * Add login to user.
   * @param Login $login
   */
  public function addLogin(Login $login) {
    $this->logins->add($login);
  }


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

  /**
   * Returns true if the user entity is associated with a local login entity.
   * @return bool
   */
  public function hasLocalAccounts(): bool {
    return !$this->logins->isEmpty();
  }

  /**
   * Returns true if the user entity is associated with a external login entity.
   * @return bool
   */
  public function hasExternalAccounts(): bool {
    return !$this->externalLogins->isEmpty();
  }

}
