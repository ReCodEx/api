<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gedmo\Mapping\Annotation as Gedmo;
use LogicException;

/**
 * @ORM\Entity
 * @ORM\Table(name="`group`")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class Group
{
    use DeleteableEntity;

    public function __construct(
        string $externalId,
        Instance $instance,
        User $admin = null,
        Group $parentGroup = null,
        bool $publicStats = false,
        bool $isPublic = false,
        bool $isOrganizational = false,
        bool $isDetaining = false
    ) {
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

        // If no parent group is given, the group is connected right under the root group
        if ($parentGroup === null) {
            $parentGroup = $instance->getRootGroup();
        }

        $this->parentGroup = $parentGroup;
        if ($parentGroup !== null) { // this still might be true, when the root group of an instance is being created
            $parentGroup->addChildGroup($this);
        }

        $this->isOrganizational = $isOrganizational;
        $this->isDetaining = $isDetaining;

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

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function isPrivate(): bool
    {
        return !$this->isPublic;
    }

    public function statsArePublic(): bool
    {
        return $this->publicStats;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $archivedAt = null;

    /**
     * Flag that helps determine whether a group has been archived explicitly.
     * That affects the modifications (moving and excavating group to/from archive).
     * @ORM\Column(type="boolean")
     */
    protected $directlyArchived = false;

    /**
     * Recursively modifies the archived date to simulate inheritance of this state.
     * If any descendant group is marked as directly archived, it is not modified (along with the entire subtree).
     * @param DateTime|null $date new value set to archivedAt property
     */
    private function setArchivingStatus(?DateTime $date)
    {
        $this->archivedAt = $date;
        foreach ($this->getChildGroups() as $childGroup) {
            if (!$childGroup->isDirectlyArchived()) {
                $childGroup->setArchivingStatus($date);
            }
        }
    }

    public function archive(DateTime $date = null)
    {
        $date = $date ?? new DateTime();
        $this->setArchivingStatus($date);
        $this->directlyArchived = true;
    }

    public function undoArchiving()
    {
        if (!$this->isDirectlyArchived()) {
            throw new LogicException("Only on directly archived groups can undo the archiving status.");
        }
        $this->directlyArchived = false;

        $parent = $this->getParentGroup();
        if (!$parent || !$parent->isArchived()) {
            $this->setArchivingStatus(null);
        }
    }

    /**
     * A group is considered archived if it or any of its parents has the `isArchived` flag set
     * @return bool
     */
    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }


    /**
     * Get the archiving date of this group (or the nearest archived parent).
     * If the group is not archived, null is returned.
     * @return DateTime|null
     */
    public function getArchivedAt(): ?DateTime
    {
        return $this->archivedAt;
    }

    /**
     * @return bool true only if the group itself was explicitly marked as archived
     * (If a group has archived date but is not directly archived, then it was archived transitionally.)
     */
    public function isDirectlyArchived(): bool
    {
        return $this->directlyArchived;
    }

    /**
     * @ORM\Column(type="boolean", options={"default":0})
     */
    protected $isOrganizational = false;

    public function isOrganizational(): bool
    {
        return $this->isOrganizational;
    }

    public function setOrganizational($value = true)
    {
        $this->isOrganizational = $value;
    }

    /**
     * @ORM\Column(type="boolean", options={"default":0})
     * Students cannot leave detaining groups on their own (supervisor can remove them).
     */
    protected $isDetaining = false;

    public function isDetaining(): bool
    {
        return $this->isDetaining;
    }

    public function setDetaining($value = true)
    {
        $this->isDetaining = $value;
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
    public function getAllSubgroups()
    {
        $subtrees = $this->getChildGroups()->map(
            function (Group $group) {
                return $group->getAllSubgroups();
            }
        );
        return array_merge($this->getChildGroups()->getValues(), ...$subtrees);
    }

    /**
     * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="groups")
     */
    protected $exercises;

    public function getExercises()
    {
        return $this->exercises->filter(
            function (Exercise $exercise) {
                return $exercise->getDeletedAt() === null;
            }
        );
    }

    /**
     * @ORM\ManyToOne(targetEntity="Instance", inversedBy="groups")
     */
    protected $instance;

    public function getInstance(): ?Instance
    {
        return $this->instance->isDeleted() ? null : $this->instance;
    }

    public function hasValidLicence()
    {
        $instance = $this->getInstance();
        return $instance && $instance->hasValidLicence();
    }

    /**
     * @ORM\OneToMany(targetEntity="GroupMembership", mappedBy="group", cascade={"all"})
     */
    protected $memberships;

    protected function getMemberships()
    {
        return $this->memberships->filter(
            function (GroupMembership $membership) {
                return $membership->getUser()->getDeletedAt() === null;
            }
        );
    }

    public function addMembership(GroupMembership $membership)
    {
        $this->memberships->add($membership);
    }

    public function removeMembership(GroupMembership $membership)
    {
        $this->getMemberships()->removeElement($membership);
    }

    protected function getActiveMemberships()
    {
        $filter = Criteria::create()
            ->where(Criteria::expr()->eq("status", GroupMembership::STATUS_ACTIVE));

        return $this->getMemberships()->matching($filter);
    }

    /**
     * Return all active members depending on specified type
     * @param string $type
     * @return Collection|static
     */
    protected function getActiveMembers(string $type)
    {
        if ($type == GroupMembership::TYPE_ALL) {
            $members = $this->getActiveMemberships();
        } else {
            $filter = Criteria::create()->where(Criteria::expr()->eq("type", $type));
            $members = $this->getActiveMemberships()->matching($filter);
        }

        return $members->map(
            function (GroupMembership $membership) {
                return $membership->getUser();
            }
        );
    }

    /**
     * Get all members of the group.
     * @return ArrayCollection|Collection
     */
    public function getMembers()
    {
        $members = $this->getActiveMemberships();
        return $members->map(
            function (GroupMembership $membership) {
                return $membership->getUser();
            }
        );
    }

    public function getStudents()
    {
        return $this->getActiveMembers(GroupMembership::TYPE_STUDENT);
    }

    public function isStudentOf(User $user)
    {
        return $this->getStudents()->contains($user);
    }

    public function getSupervisors()
    {
        return $this->getActiveMembers(GroupMembership::TYPE_SUPERVISOR);
    }

    public function isSupervisorOf(User $user)
    {
        return $this->getSupervisors()->contains($user);
    }

    public function isMemberOf(User $user)
    {
        return $this->getActiveMembers(GroupMembership::TYPE_ALL)->contains($user);
    }

    /**
     * Is member of this group or any subgroup.
     * @note Is member or supervisor or admin, whole package of members.
     * @param User $user
     * @return bool
     */
    public function isMemberOfSubgroup(User $user)
    {
        if ($this->isAdminOf($user) || $this->isMemberOf($user)) {
            return true;
        }

        foreach ($this->getChildGroups() as $childGroup) {
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
    public function getPrimaryAdmins()
    {
        return $this->primaryAdmins->filter(
            function (User $admin) {
                return $admin->getDeletedAt() === null;
            }
        );
    }

    /**
     * True if user is admin of this particular group.
     * @param User $user
     * @return bool
     */
    public function isPrimaryAdminOf(User $user)
    {
        $admins = $this->getPrimaryAdminsIds();
        return array_search($user->getId(), $admins, true) !== false;
    }

    /**
     * @param User $user
     */
    public function addPrimaryAdmin(User $user)
    {
        $this->primaryAdmins->add($user);
    }

    /**
     * @param User $user
     * @return bool
     */
    public function removePrimaryAdmin(User $user)
    {
        return $this->primaryAdmins->removeElement($user);
    }

    /**
     * @return array
     */
    public function getPrimaryAdminsIds()
    {
        return $this->getPrimaryAdmins()->map(
            function (User $admin) {
                return $admin->getId();
            }
        )->getValues();
    }

    /**
     * @return array
     */
    public function getAdmins()
    {
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
    public function getAdminsIds()
    {
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
    public function isAdminOf(User $user)
    {
        $admins = $this->getAdminsIds();
        return array_search($user->getId(), $admins, true) !== false;
    }

    /**
     * User is admin of subgroup or supervisor of any subgroup.
     * @param User $user
     * @return bool
     */
    public function isAdminOrSupervisorOfSubgroup(User $user): bool
    {
        if ($this->isAdminOf($user) || $this->isSupervisorOf($user)) {
            return true;
        }

        foreach ($this->getChildGroups() as $childGroup) {
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
     * @param ArrayCollection $assignments List of assignments
     * @return string[]
     */
    public function getAssignmentsIds($assignments = null): array
    {
        $assignments = $assignments === null ? $this->getAssignments() : $assignments;
        return $assignments->map(
            function (Assignment $a) {
                return $a->getId();
            }
        )->getValues();
    }

    /**
     * @return Collection
     */
    public function getAssignments()
    {
        return $this->assignments->filter(
            function (Assignment $assignment) {
                return $assignment->getDeletedAt() === null;
            }
        );
    }

    /**
     * @ORM\OneToMany(targetEntity="ShadowAssignment", mappedBy="group")
     */
    protected $shadowAssignments;

    /**
     * Map collection of shadow assignments to an array of its ID's
     * @return string[]
     */
    public function getShadowAssignmentsIds(): array
    {
        return $this->shadowAssignments->map(
            function (ShadowAssignment $a) {
                return $a->getId();
            }
        )->getValues();
    }

    /**
     * @return Collection
     */
    public function getShadowAssignments()
    {
        return $this->shadowAssignments->filter(
            function (ShadowAssignment $pointsAssignment) {
                return $pointsAssignment->getDeletedAt() === null;
            }
        );
    }

    public function getMaxPoints(): int
    {
        $pointsAss = array_reduce(
            $this->getAssignments()->getValues(),
            function ($carry, Assignment $assignment) {
                return $carry + $assignment->getGroupPoints();
            },
            0
        );
        $pointsShadow = array_reduce(
            $this->getShadowAssignments()->getValues(),
            function ($carry, ShadowAssignment $shadowAssignment) {
                return $carry + $shadowAssignment->getGroupPoints();
            },
            0
        );

        return $pointsAss + $pointsShadow;
    }

    public function getLocalizedTextByLocale(string $locale): ?LocalizedGroup
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
        $first = $this->localizedTexts->matching($criteria)->first();
        return $first === false ? null : $first;
    }

    public function addLocalizedText(LocalizedGroup $group)
    {
        $this->localizedTexts->add($group);
        $group->setGroup($this);
    }

    public function getLocalizedTexts(): Collection
    {
        return $this->localizedTexts;
    }

    /**
     * Get identifications of groups in descending order.
     * @return string[]
     */
    public function getParentGroupsIds(): array
    {
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
    public function getChildGroupsIds(): array
    {
        return $this->getChildGroups()->map(
            function (Group $group) {
                return $group->getId();
            }
        )->getValues();
    }

    /**
     * Get identification of public child groups.
     * @return array
     */
    public function getPublicChildGroupsIds(): array
    {
        return $this->getChildGroups()->filter(
            function (Group $group) {
                return $group->isPublic();
            }
        )->map(
            function (Group $group) {
                return $group->getId();
            }
        )->getValues();
    }

    public function getPublicChildGroups(): Collection
    {
        return $this->getChildGroups()->filter(
            function (Group $group) {
                return $group->isPublic();
            }
        );
    }

    public function getChildGroups(): Collection
    {
        return $this->childGroups->filter(
            function (Group $group) {
                return $group->getDeletedAt() === null;
            }
        );
    }

    public function getParentGroup(): ?Group
    {
        if ($this->parentGroup !== null) {
            return $this->parentGroup->isDeleted() ? null : $this->parentGroup;
        }

        return $this->parentGroup;
    }


    /**
     * Change the parent root of the group.
     * Note that this is low level function. It is callers responsibility to verify that such change is valid.
     * @param Group $newParent Target parent under which this group is relocated
     */
    public function setParentGroup(Group $newParent)
    {
        if ($this->parentGroup !== $newParent) {
            if ($this->parentGroup->isArchived() !== $newParent->isArchived() && !$this->isDirectlyArchived()) {
                // archived state changes, we need to fix it
                $this->setArchivingStatus($newParent->getArchivedAt());
            }

            if ($this->parentGroup) {
                $this->parentGroup->removeChildGroup($this);
            }

            $this->parentGroup = $newParent;
            $newParent->addChildGroup($this);
        }
    }

    ////////////////////////////////////////////////////////////////////////////

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getThreshold(): ?float
    {
        return $this->threshold;
    }

    public function setThreshold(?float $threshold): void
    {
        $this->threshold = $threshold;
    }

    public function getPublicStats(): bool
    {
        return $this->publicStats;
    }

    public function setPublicStats(bool $publicStats): void
    {
        $this->publicStats = $publicStats;
    }

    public function setIsPublic(bool $isPublic): void
    {
        $this->isPublic = $isPublic;
    }

    public function addChildGroup(Group $group): void
    {
        $this->childGroups->add($group);
    }

    public function removeChildGroup(Group $group): void
    {
        $this->childGroups->removeElement($group);
    }

    public function addAssignment(Assignment $assignment): void
    {
        $this->assignments->add($assignment);
    }

    public function addShadowAssignment(ShadowAssignment $shadowAssignment): void
    {
        $this->shadowAssignments->add($shadowAssignment);
    }
}
