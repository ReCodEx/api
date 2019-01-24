<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @ORM\Table(name="`group`")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method addAssignment(Assignment $assignment)
 * @method addShadowAssignment(ShadowAssignment $assignment)
 * @method addChildGroup(Group $group)
 * @method string getExternalId()
 * @method string getDescription()
 * @method float getThreshold()
 * @method Instance getInstance()
 * @method setExternalId(string $id)
 * @method bool getPublicStats()
 * @method setPublicStats(bool $areStatsPublic)
 * @method setIsPublic(bool $isGroupPublic)
 * @method setThreshold(float $threshold)
 */
class Group
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;
  use DeleteableEntity;

  public function __construct(
      string $externalId,
      Instance $instance,
      User $admin = null,
      Group $parentGroup = null,
      bool $publicStats = false,
      bool $isPublic = false,
      bool $isOrganizational = false) {
    $this->externalId = $externalId;
    $this->memberships = new ArrayCollection();
    $this->primaryAdmins = new ArrayCollection();
    $this->instance = $instance;
    $this->publicStats = $publicStats;
    $this->isPublic = $isPublic;
    $this->childGroups = new ArrayCollection();
    $this->assignments = new ArrayCollection();
    $this->shadowAssignments = new ArrayCollection();
    $this->exercises = new ArrayCollection();
    $this->localizedTexts = new ArrayCollection();

    if ($admin !== null) {
      $this->primaryAdmins->add($admin);
      $admin->makeSupervisorOf($this);
    }

    $this->parentGroup = $parentGroup;
    if ($parentGroup !== null) {
      $this->parentGroup->addChildGroup($this);
    }

    $this->isOrganizational = $isOrganizational;

    $instance->addGroup($this);
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $externalId;

  /**
   * @ORM\OneToMany(targetEntity="LocalizedGroup", mappedBy="group")
   * @var ArrayCollection
   */
  protected $localizedTexts;

  /**
   * @ORM\Column(type="float", nullable=true)
   */
  protected $threshold;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $publicStats;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  public function isPublic(): bool {
    return $this->isPublic;
  }

  public function isPrivate(): bool {
    return !$this->isPublic;
  }

  public function statsArePublic(): bool {
    return $this->publicStats;
  }

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $archivationDate;

  public function archive(DateTime $date = null) {
    $date = $date ?? new DateTime();
    $this->archivationDate = $date;
  }

  public function undoArchivation() {
    $this->archivationDate = null;
  }

  /**
   * A group is considered archived if it or any of its parents has the `isArchived` flag set
   * @return bool
   */
  public function isArchived(): bool {
    $group = $this;

    while ($group !== null) {
      if ($group->isDirectlyArchived()) {
        return true;
      }

      $group = $group->getParentGroup();
    }

    return false;
  }


  /**
   * Get the archiving date of this group (or the nearest archived parent).
   * If the group is not archived, null is returned.
   * @return DateTime|null
   */
  public function getArchivationDate(): ?DateTime {
    $group = $this;

    while ($group !== null) {
      if ($group->isDirectlyArchived()) {
        return $group->archivationDate;
      }

      $group = $group->getParentGroup();
    }

    return null;
  }

  /**
   * @return bool true only if the group itself (and not its parent) was marked as archived
   */
  public function isDirectlyArchived(): bool {
    return $this->archivationDate !== null;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isOrganizational = false;

  public function isOrganizational(): bool {
    return $this->isOrganizational;
  }

  public function setOrganizational($value = true) {
    $this->isOrganizational = $value;
  }

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="childGroups")
   */
  protected $parentGroup;

  /**
   * @ORM\OneToMany(targetEntity="Group", mappedBy="parentGroup")
   */
  protected $childGroups;

  /**
   * Recursively merge all the subgroups into a flat array of groups.
   * @return array
   */
  public function getAllSubgroups() {
    $subtrees = $this->childGroups->map(function (Group $group) {
      return $group->getAllSubgroups();
    });
    return array_merge($this->childGroups->getValues(), ...$subtrees);
  }

  /**
   * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="groups")
   */
  protected $exercises;

  public function getExercises() {
    return $this->exercises->filter(function (Exercise $exercise) {
      return $exercise->getDeletedAt() === null;
    });
  }

  /**
   * @ORM\ManyToOne(targetEntity="Instance", inversedBy="groups")
   */
  protected $instance;

  public function hasValidLicence() {
    $instance = $this->getInstance();
    return $instance && $instance->hasValidLicence();
  }

  /**
   * @ORM\OneToMany(targetEntity="GroupMembership", mappedBy="group", cascade={"all"})
   */
  protected $memberships;

  protected function getMemberships() {
    return $this->memberships->filter(function (GroupMembership $membership) {
      return $membership->getUser()->getDeletedAt() === null;
    });
  }

  public function addMembership(GroupMembership $membership) {
    $this->memberships->add($membership);
  }

  public function removeMembership(GroupMembership $membership) {
    $this->getMemberships()->removeElement($membership);
  }

  protected function getActiveMemberships() {
    $filter = Criteria::create()
                ->where(Criteria::expr()->eq("status", GroupMembership::STATUS_ACTIVE));

    return $this->getMemberships()->matching($filter);
  }

  /**
   * Return all active members depending on specified type
   * @param string $type
   * @return Collection|static
   */
  protected function getActiveMembers(string $type) {
    if ($type == GroupMembership::TYPE_ALL) {
      $members = $this->getActiveMemberships();
    } else {
      $filter = Criteria::create()->where(Criteria::expr()->eq("type", $type));
      $members = $this->getActiveMemberships()->matching($filter);
    }

    return $members->map(
      function(GroupMembership $membership) {
        return $membership->getUser();
      }
    );
  }

  /**
   * Get all members of the group.
   * @return ArrayCollection|Collection
   */
  public function getMembers() {
    $members = $this->getActiveMemberships();
    return $members->map(function (GroupMembership $membership) {
      return $membership->getUser();
    });
  }

  public function getStudents() {
    return $this->getActiveMembers(GroupMembership::TYPE_STUDENT);
  }

  public function isStudentOf(User $user) {
    return $this->getStudents()->contains($user);
  }

  public function getSupervisors() {
    return $this->getActiveMembers(GroupMembership::TYPE_SUPERVISOR);
  }

  public function isSupervisorOf(User $user) {
    return $this->getSupervisors()->contains($user);
  }

  public function isMemberOf(User $user) {
    return $this->getActiveMembers(GroupMembership::TYPE_ALL)->contains($user);
  }

  /**
   * Is member of this group or any subgroup.
   * @note Is member or supervisor or admin, whole package of members.
   * @param User $user
   * @return bool
   */
  public function isMemberOfSubgroup(User $user) {
    if ($this->isAdminOf($user) || $this->isMemberOf($user)) {
      return true;
    }

    foreach ($this->childGroups as $childGroup) {
      if ($childGroup->isMemberOfSubgroup($user)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @ORM\ManyToMany(targetEntity="User")
   */
  protected $primaryAdmins;

  /**
   * @return Collection
   */
  public function getPrimaryAdmins() {
    return $this->primaryAdmins->filter(function (User $admin) {
      return $admin->getDeletedAt() === null;
    });
  }

  /**
   * True if user is admin of this particular group.
   * @param User $user
   * @return bool
   */
  public function isPrimaryAdminOf(User $user) {
    $admins = $this->getPrimaryAdminsIds();
    return array_search($user->getId(), $admins, true) !== false;
  }

  /**
   * @param User $user
   */
  public function addPrimaryAdmin(User $user) {
    $this->primaryAdmins->add($user);
  }

  /**
   * @param User $user
   * @return bool
   */
  public function removePrimaryAdmin(User $user) {
    return $this->primaryAdmins->removeElement($user);
  }

  /**
   * @return array
   */
  public function getPrimaryAdminsIds() {
    return $this->getPrimaryAdmins()->map(function (User $admin) {
      return $admin->getId();
    })->getValues();
  }

  /**
   * @return array
   */
  public function getAdmins() {
    $group = $this;
    $admins = [];
    while ($group !== null) {
      $admins = array_merge($admins, $group->getPrimaryAdmins()->getValues());
      $group = $group->getParentGroup();
    }

    return array_values(array_unique($admins));
  }

  /**
   * @return array
   */
  public function getAdminsIds() {
    $group = $this;
    $admins = [];
    while ($group !== null) {
      $admins = array_merge($admins, $group->getPrimaryAdminsIds());
      $group = $group->getParentGroup();
    }

    return array_values(array_unique($admins));
  }

  /**
   * User is admin of a group when he is admin of any parent group.
   * @param User $user
   * @return bool
   */
  public function isAdminOf(User $user) {
    $admins = $this->getAdminsIds();
    return array_search($user->getId(), $admins, true) !== false;
  }

  /**
   * User is admin of subgroup or supervisor of any subgroup.
   * @param User $user
   * @return bool
   */
  public function isAdminOrSupervisorOfSubgroup(User $user): bool {
    if ($this->isAdminOf($user) || $this->isSupervisorOf($user)) {
      return true;
    }

    foreach ($this->childGroups as $childGroup) {
      if ($childGroup->isAdminOrSupervisorOfSubgroup($user)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @ORM\OneToMany(targetEntity="Assignment", mappedBy="group")
   */
  protected $assignments;

  /**
   * Map collection of assignments to an array of its ID's
   * @param ArrayCollection   $assignments  List of assignments
   * @return string[]
   */
  public function getAssignmentsIds($assignments = null): array {
    $assignments = $assignments === null ? $this->getAssignments() : $assignments;
    return $assignments->map(function (Assignment $a) {
      return $a->getId();
    })->getValues();
  }

  /**
   * @return Collection
   */
  public function getAssignments() {
    return $this->assignments->filter(function (Assignment $assignment) {
      return $assignment->getDeletedAt() === null;
    });
  }

  /**
   * @ORM\OneToMany(targetEntity="ShadowAssignment", mappedBy="group")
   */
  protected $shadowAssignments;

  /**
   * Map collection of shadow assignments to an array of its ID's
   * @return string[]
   */
  public function getShadowAssignmentsIds(): array {
    return $this->shadowAssignments->map(function (ShadowAssignment $a) {
      return $a->getId();
    })->getValues();
  }

  /**
   * @return Collection
   */
  public function getShadowAssignments() {
    return $this->shadowAssignments->filter(function (ShadowAssignment $pointsAssignment) {
      return $pointsAssignment->getDeletedAt() === null;
    });
  }

  public function getMaxPoints(): int {
    $pointsAss = array_reduce(
      $this->getAssignments()->getValues(),
      function ($carry, Assignment $assignment) { return $carry + $assignment->getGroupPoints(); },
      0
    );
    $pointsShadow = array_reduce(
      $this->getShadowAssignments()->getValues(),
      function ($carry, ShadowAssignment $shadowAssignment) { return $carry + $shadowAssignment->getGroupPoints(); },
      0
    );

    return $pointsAss + $pointsShadow;
  }

  public function getLocalizedTextByLocale(string $locale): ?LocalizedGroup {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
    $first = $this->localizedTexts->matching($criteria)->first();
    return $first === false ? null : $first;
  }

  public function addLocalizedText(LocalizedGroup $group) {
    $this->localizedTexts->add($group);
    $group->setGroup($this);
  }

  public function getLocalizedTexts(): Collection {
    return $this->localizedTexts;
  }

  /**
   * Student can view only public assignments.
   */
  public function getPublicAssignments() {
    return $this->getAssignments()->filter(
      function (Assignment $assignment) { return $assignment->isPublic(); }
    );
  }

  /**
   * Get identifications of groups in descending order.
   * @return string[]
   */
  public function getParentGroupsIds(): array {
    $group = $this->getParentGroup();
    $parents = [];
    while ($group !== null) {
      $parents[] = $group->getId();
      $group = $group->getParentGroup();
    }

    return array_values(array_reverse($parents));
  }

  /**
   * Get identification of all child groups.
   * @return array
   */
  public function getChildGroupsIds(): array {
    return $this->getChildGroups()->map(
      function(Group $group) {
        return $group->getId();
      }
    )->getValues();
  }

  /**
   * Get identification of public child groups.
   * @return array
   */
  public function getPublicChildGroupsIds(): array {
    return $this->getChildGroups()->filter(
      function(Group $group) {
        return $group->isPublic();
      }
    )->map(
      function(Group $group) {
        return $group->getId();
      }
    )->getValues();
  }

  public function getPublicChildGroups(): Collection {
    return $this->getChildGroups()->filter(
      function(Group $group) {
        return $group->isPublic();
      }
    );
  }

  public function getChildGroups(): Collection {
    return $this->childGroups->filter(function (Group $group) {
      return $group->getDeletedAt() === null;
    });
  }

  public function getParentGroup(): ?Group {
    if ($this->parentGroup !== null) {
      return $this->parentGroup->isDeleted() ? null : $this->parentGroup;
    }

    if ($this->instance->getRootGroup() !== $this) {
      return $this->instance->getRootGroup();
    }

    return null;
  }
}
