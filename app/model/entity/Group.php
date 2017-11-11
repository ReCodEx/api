<?php

namespace App\Model\Entity;

use App\Helpers\Localizations;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gedmo\Mapping\Annotation as Gedmo;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(name="`group`")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method string getName()
 * @method DateTime getDeletedAt()
 * @method addAssignment(Assignment $assignment)
 * @method addChildGroup(Group $group)
 * @method string getExternalId()
 * @method string getDescription()
 * @method float getThreshold()
 * @method Instance getInstance()
 * @method setExternalId(string $id)
 * @method setPublicStats(bool $areStatsPublic)
 * @method setIsPublic(bool $isGroupPublic)
 * @method setThreshold(float $threshold)
 */
class Group implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
      string $externalId,
      Instance $instance,
      User $admin = NULL,
      Group $parentGroup = NULL,
      bool $publicStats = TRUE,
      bool $isPublic = TRUE) {
    $this->externalId = $externalId;
    $this->memberships = new ArrayCollection;
    $this->primaryAdmins = new ArrayCollection;
    $this->instance = $instance;
    $this->publicStats = $publicStats;
    $this->isPublic = $isPublic;
    $this->childGroups = new ArrayCollection;
    $this->assignments = new ArrayCollection;
    $this->exercises = new ArrayCollection;
    $this->localizedTexts = new ArrayCollection();

    if ($admin !== NULL) {
      $this->primaryAdmins->add($admin);
      $admin->makeSupervisorOf($this);
    }

    $this->parentGroup = $parentGroup;
    if ($parentGroup !== NULL) {
      $this->parentGroup->addChildGroup($this);
    }

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
  protected $deletedAt;

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
      return $exercise->getDeletedAt() === NULL;
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
      return $membership->getUser()->getDeletedAt() === NULL;
    });
  }

  public function addMembership(GroupMembership $membership) {
    $this->memberships->add($membership);
  }

  public function removeMembership(GroupMembership $membership) {
    $this->getMemberships()->remove($membership);
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
    return array_search($user->getId(), $admins, TRUE) !== FALSE;
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
  public function getAdminsIds() {
    $group = $this;
    $admins = [];
    while ($group !== NULL) {
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
    return array_search($user->getId(), $admins, TRUE) !== FALSE;
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
  public function getAssignmentsIds($assignments = NULL): array {
    $assignments = $assignments === NULL ? $this->getAssignments() : $assignments;
    return $assignments->map(function (Assignment $a) {
      return $a->getId();
    })->getValues();
  }

  /**
   * @return Collection
   */
  public function getAssignments() {
    return $this->assignments->filter(function (Assignment $assignment) {
      return $assignment->getDeletedAt() === NULL;
    });
  }

  public function getMaxPoints(): int {
    return array_reduce(
      $this->getAssignments()->getValues(),
      function ($carry, Assignment $assignment) { return $carry + $assignment->getGroupPoints(); },
      0
    );
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
    while ($group !== NULL) {
      $parents[] = $group->getId();
      $group = $group->getParentGroup();
    }

    return array_values(array_reverse($parents));
  }

  /**
   * Get names of parent groups in descending order.
   * @return string[]
   */
  public function getParentGroupsNames(): array {
    $group = $this->getParentGroup();
    $parentsNames = [];
    while ($group !== NULL) {
      $parentsNames[] = $group->getName();
      $group = $group->getParentGroup();
    }

    return array_values(array_reverse($parentsNames));
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

  /**
   * Get public data concerning group.
   * @param bool $canView
   * @return array
   */
  public function getPublicData(bool $canView): array {
    /** @var LocalizedGroup $primaryLocalization */
    $primaryLocalization = Localizations::getPrimaryLocalization($this->localizedTexts);

    return [
      "id" => $this->id,
      "externalId" => $this->externalId,
      "localizedTexts" => $this->localizedTexts->getValues(),
      "name" => $primaryLocalization ? $primaryLocalization->getName() : "", # BC
      "admins" => $this->getPrimaryAdmins()->map(function (User $user) {
        return $user->getPublicData();
      })->getValues(),
      "childGroups" => [
        "all" => $this->getChildGroupsIds(),
        "public" => $this->getPublicChildGroupsIds()
      ],
      "canView" => $canView
    ];
  }

  public function jsonSerialize() {
    $instance = $this->getInstance();

    /** @var LocalizedGroup $primaryLocalization */
    $primaryLocalization = Localizations::getPrimaryLocalization($this->localizedTexts);

    return [
      "id" => $this->id,
      "externalId" => $this->externalId,
      "localizedTexts" => $this->localizedTexts->getValues(),
      "name" => $primaryLocalization ? $primaryLocalization->getName() : "", # BC
      "description" => $primaryLocalization ? $primaryLocalization->getDescription() : "", # BC
      "primaryAdminsIds" => $this->getPrimaryAdminsIds(),
      "admins" => $this->getAdminsIds(),
      "supervisors" => $this->getSupervisors()->map(function(User $s) { return $s->getId(); })->getValues(),
      "students" => $this->getStudents()->map(function(User $s) { return $s->getId(); })->getValues(),
      "instanceId" => $instance ? $instance->getId() : NULL,
      "hasValidLicence" => $this->hasValidLicence(),
      "parentGroupId" => $this->parentGroup ? $this->parentGroup->getId() : NULL,
      "parentGroupsIds" => $this->getParentGroupsIds(),
      "childGroups" => [
        "all" => $this->getChildGroupsIds(),
        "public" => $this->getPublicChildGroupsIds()
      ],
      "assignments" => [
        "all" => $this->getAssignmentsIds(),
        "public" => $this->getAssignmentsIds($this->getPublicAssignments())
      ],
      "publicStats" => $this->publicStats,
      "isPublic" => $this->isPublic,
      "threshold" => $this->threshold
    ];
  }

  public function getChildGroups() {
    return $this->childGroups->filter(function (Group $group) {
      return $group->getDeletedAt() === NULL;
    });
  }

  public function getParentGroup(): ?Group {
    if ($this->parentGroup !== NULL) {
      return $this->parentGroup;
    }

    if ($this->instance->getRootGroup() !== $this) {
      return $this->instance->getRootGroup();
    }

    return null;
  }
}
