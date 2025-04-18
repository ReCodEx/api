<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use LogicException;
use InvalidArgumentException;

/**
 * @ORM\Entity
 * @ORM\Table(name="`group`")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 * Regular groups have students and offer them assignments. There are two special group types:
 * - organizational (cannot have students nor assignments, but may have sub-groups)
 *   indicated by isOrganizational column (flag)
 * - exam (activity in this group is restricted to very short period in time when an exam is scheduled)
 *   indicated by non-null values of examBegin and examEnd columns
 */
class Group
{
    use DeletableEntity;

    public function __construct(
        string $externalId,
        Instance $instance,
        ?User $admin = null,
        ?Group $parentGroup = null,
        bool $publicStats = false,
        bool $isPublic = false,
        bool $isOrganizational = false,
        bool $isDetaining = false,
        bool $isExam = false,
    ) {
        $this->externalId = $externalId;
        $this->memberships = new ArrayCollection();
        $this->instance = $instance;
        $this->publicStats = $publicStats;
        $this->isPublic = $isPublic;
        $this->childGroups = new ArrayCollection();
        $this->assignments = new ArrayCollection();
        $this->shadowAssignments = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->exercises = new ArrayCollection();
        $this->localizedTexts = new ArrayCollection();
        $this->exams = new ArrayCollection();
        $this->externalAttributes = new ArrayCollection();

        if ($admin !== null) {
            $this->addPrimaryAdmin($admin);
        }

        // If no parent group is given, the group is connected right under the root group
        if ($parentGroup === null) {
            $parentGroup = $instance->getRootGroup();
        }

        $this->parentGroup = $parentGroup;
        if ($parentGroup !== null) { // this still might be true, when the root group of an instance is being created
            $parentGroup->addChildGroup($this);
        }

        if ($isOrganizational && $isExam) {
            throw new LogicException("A group cannot be both organizational and exam group.");
        }

        $this->isOrganizational = $isOrganizational;
        $this->isExam = $isExam;
        $this->isDetaining = $isDetaining;

        $instance->addGroup($this);
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\Column(type="string", nullable=true)
     * DEPRECATED in favor of external attributes
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
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $pointsLimit;

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

    public function archive(?DateTime $date = null)
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

    /**
     * @ORM\Column(type="boolean", options={"default":0})
     * Students cannot leave detaining groups on their own (supervisor can remove them).
     */
    protected $isDetaining = false;

    /**
     * @ORM\Column(type="boolean", options={"default":0})
     * The group is dedicated to examination. This is used mainly for selective visualization
     * and to make the "exam" flag of the assignments set as default.
     * This flag is independent of the exam begin-end dates which are used for security purposes.
     */
    protected $isExam = false;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * When an exam in this groups begins. In the exam period, a user must lock in a group to be allowed
     * submitting solutions. This is completely independent of the isExam flag.
     */
    protected $examBegin = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * When an exam in this groups ends. In the exam period, a user must lock in a group to be allowed
     * submitting solutions. This is completely independent of the isExam flag.
     */
    protected $examEnd = null;

    /**
     * @ORM\Column(type="boolean")
     * Whether the group-lock for the exam should be strict
     * (under strict lock, the user cannot read data from other groups).
     */
    protected $examLockStrict = false;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="GroupExam", mappedBy="group",
     *                cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"begin" = "DESC"})
     */
    protected $exams;

    /**
     * Switch the group into an exam group by setting the begin and end dates of the exam.
     * @param DateTime $begin when the exam starts
     * @param DateTime $end when the exam ends
     * @param bool $strict if true, locked users cannot access other groups (for reading)
     */
    public function setExamPeriod(DateTime $begin, DateTime $end, bool $strict = false): void
    {
        // asserts
        if ($begin >= $end) {
            throw new InvalidArgumentException("The begin date must be before the end date.");
        }

        if ($this->isArchived()) {
            throw new LogicException("Unable to set exam in an archived group.");
        }

        $this->examBegin = $begin;
        $this->examEnd = $end;
        $this->examLockStrict = $strict;
        $this->isOrganizational = false;
    }

    /**
     * Clear the exam status (the begin and end date).
     */
    public function removeExamPeriod(): void
    {
        $this->examBegin = null;
        $this->examEnd = null;
        $this->examLockStrict = false;
    }

    /**
     * Whether this is an exam group.
     * @return bool true if an exam is set in this group
     */
    public function hasExamPeriodSet(?DateTime $at = null): bool
    {
        $at = $at ?? new DateTime();
        return $this->examBegin !== null && $this->examEnd !== null && $this->examEnd > $at;
    }

    public function isExamLockStrict(): bool
    {
        return $this->examLockStrict;
    }

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="GroupExternalAttribute", mappedBy="group", cascade={"all"}, orphanRemoval=true)
     */
    protected $externalAttributes;

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
     * @var Collection
     * @ORM\OneToMany(targetEntity="GroupInvitation", mappedBy="group",
     *                cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"createdAt" = "DESC"})
     */
    protected $invitations;

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

    public function hasValidLicense()
    {
        $instance = $this->getInstance();
        return $instance && $instance->hasValidLicense();
    }

    /**
     * @ORM\OneToMany(targetEntity="GroupMembership", mappedBy="group", cascade={"all"})
     */
    protected $memberships;

    /**
     * Get the membership entity for this group and given user (null is returned if no membership exists).
     * @param User $user
     * @return GroupMembership|null
     */
    public function getMembershipOfUser(User $user): ?GroupMembership
    {
        $memberships = $this->memberships->filter(
            function (GroupMembership $membership) use ($user) {
                return !$membership->isInherited() && $membership->getUser()->getId() === $user->getId();
            }
        );

        return $memberships->isEmpty() ? null : $memberships->first();
    }

    /**
     * @param string[] $types allowed membership types (empty array = all)
     * @param bool|null $inherited flag indicating how to filter inherited memberships
     *                  (null = no filter, true = only inherited, false = only direct)
     * @return Collection
     */
    private function getMembershipsInternal(array $types, ?bool $inherited = null)
    {
        $memberships = $this->memberships->filter(
            function (GroupMembership $membership) {
                return $membership->getUser()->getDeletedAt() === null;
            }
        );

        $filters = [];
        if ($types) {
            $orTypes = array_map(function ($type) {
                return Criteria::expr()->eq("type", $type);
            }, $types);
            $filters[] = Criteria::expr()->orX(...$orTypes);
        }

        if ($inherited !== null) {
            $filters[] = $inherited
                ? Criteria::expr()->neq("inheritedFrom", null)
                : Criteria::expr()->isNull("inheritedFrom");
        }

        if ($filters) {
            $filter = Criteria::create()->where(Criteria::expr()->andX(...$filters));
            $memberships = $memberships->matching($filter);
        }

        return $memberships;
    }

    /**
     * Return all direct members depending on specified type
     * @param string[] ...$types
     * @return Collection
     */
    public function getMemberships(...$types)
    {
        return $this->getMembershipsInternal($types, false); // false = exclude inherited
    }

    /**
     * Return all inherited members depending on specified type
     * @param string[] ...$types
     * @return Collection
     */
    public function getInheritedMemberships(...$types)
    {
        return $this->getMembershipsInternal($types, true); // true = only inherited
    }

    public function addMembership(GroupMembership $membership): void
    {
        $this->memberships->add($membership);
    }

    public function inheritMembership(GroupMembership $membership): GroupMembership
    {
        $inheritedMembership = new GroupMembership(
            $this, // this group is the new membership group
            $membership->getUser(),
            $membership->getType(),
            $membership->getGroup() // old group is recorded in "inherited from"
        );
        $this->addMembership($inheritedMembership);
        return $inheritedMembership;
    }

    public function removeMembership(GroupMembership $membership): bool
    {
        return $this->memberships->removeElement($membership);
    }

    /**
     * Get all members of the group of given type
     * @param string[] ...$types
     * @return ReadableCollection
     */
    public function getMembers(...$types): ReadableCollection
    {
        $members = $this->getMemberships(...$types);
        return $members->map(
            function (GroupMembership $membership) {
                return $membership->getUser();
            }
        )->filter(
            function (User $user) {
                return $user->getDeletedAt() === null;
            }
        );
    }

    /**
     * This version is optimized for fetching IDs only (as we often need only IDs).
     * @param string[] ...$types
     * @return string[] ids
     */
    private function getMembersIds(...$types): array
    {
        $memberships = $this->getMemberships(...$types);
        return $memberships->map(
            function (GroupMembership $membership) {
                return $membership->getUser()->getId();
            }
        )->getValues();
    }

    public function getStudents()
    {
        return $this->getMembers(GroupMembership::TYPE_STUDENT);
    }

    public function getStudentsIds()
    {
        return $this->getMembersIds(GroupMembership::TYPE_STUDENT);
    }

    public function isStudentOf(User $user): bool
    {
        return $this->getStudents()->contains($user);
    }

    public function getSupervisors()
    {
        return $this->getMembers(GroupMembership::TYPE_SUPERVISOR);
    }

    public function getSupervisorsIds()
    {
        return $this->getMembersIds(GroupMembership::TYPE_SUPERVISOR);
    }

    public function isSupervisorOf(User $user): bool
    {
        return $this->getSupervisors()->contains($user);
    }

    public function getObservers()
    {
        return $this->getMembers(GroupMembership::TYPE_OBSERVER);
    }

    public function getObserversIds()
    {
        return $this->getMembersIds(GroupMembership::TYPE_OBSERVER);
    }

    public function isObserverOf(User $user): bool
    {
        return $this->getObservers()->contains($user);
    }

    public function isMemberOf(User $user): bool
    {
        return $this->getMembers()->contains($user);
    }

    /**
     * Is member of this group or any subgroup.
     * @note Is member or supervisor or admin, whole package of members.
     * @param User $user
     * @return bool
     */
    public function isMemberOfSubgroup(User $user): bool
    {
        if ($this->isMemberOf($user) || $this->isAdminOf($user)) {
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
     * @return ReadableCollection
     */
    public function getPrimaryAdmins(): ReadableCollection
    {
        return $this->getMembers(GroupMembership::TYPE_ADMIN);
    }

    /**
     * True if user is admin of this particular group.
     * @param User $user
     * @return bool
     */
    public function isPrimaryAdminOf(User $user): bool
    {
        $admins = $this->getPrimaryAdminsIds();
        return array_search($user->getId(), $admins, true) !== false;
    }

    /**
     * @param User $user
     */
    public function addPrimaryAdmin(User $user): void
    {
        $membership = $this->getMembershipOfUser($user);
        if ($membership === null) {
            $membership = new GroupMembership($this, $user, GroupMembership::TYPE_ADMIN);
            $this->addMembership($membership);
        } elseif ($membership->getType() !== GroupMembership::TYPE_ADMIN) {
            $membership->setType(GroupMembership::TYPE_ADMIN);
        }
    }

    /**
     * @param User $user
     * @return bool
     */
    public function removePrimaryAdmin(User $user): bool
    {
        $membership = $this->getMembershipOfUser($user);
        if ($membership !== null && $membership->getType() === GroupMembership::TYPE_ADMIN) {
            return $this->removeMembership($membership);
        } else {
            return false;
        }
    }

    /**
     * @return array keys are user IDs, values are all true
     */
    private function getAdminIdsInternal(bool $inherited): array
    {
        $group = $this;
        $admins = []; // key is user ID, value is true
        while ($group !== null) {
            // getMembershipsInternal inherited flag goes: true = only inherited, false = only direct, null = all
            $directAdmins = $group->getMembershipsInternal([GroupMembership::TYPE_ADMIN], $inherited ? null : false);
            foreach ($directAdmins as $membership) {
                $admins[$membership->getUser()->getId()] = true;
            }
            $group = $inherited ? $group->getParentGroup() : null;
        }

        return $admins;
    }

    /**
     * Return IDs of users which are explicitly listed as direct admins of this group.
     * @return string[]
     */
    public function getPrimaryAdminsIds(): array
    {
        return array_keys($this->getAdminIdsInternal(false));
    }

    /**
     * Return IDs of all users that have admin privileges to this group (including inherited).
     * @return string[]
     */
    public function getAdminsIds(): array
    {
        return array_keys($this->getAdminIdsInternal(true));
    }

    /**
     * User is admin of a group when he is admin of any parent group.
     * @param User $user
     * @return bool
     */
    public function isAdminOf(User $user): bool
    {
        $admins = $this->getAdminIdsInternal(true);
        return array_key_exists($user->getId(), $admins);
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
     * User is admin, supervisor, or observer of this group or any subgroup.
     * @param User $user
     * @return bool
     */
    public function isNonStudentMemberOfSubgroup(User $user): bool
    {
        if ($this->isAdminOf($user) || $this->isSupervisorOf($user) || $this->isObserverOf($user)) {
            return true;
        }

        foreach ($this->getChildGroups() as $childGroup) {
            if ($childGroup->isNonStudentMemberOfSubgroup($user)) {
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
     * Return all localized texts as an array indexed by locales.
     * @return array
     */
    public function getLocalizedTextsAssocArray(): array
    {
        $result = [];
        foreach ($this->getLocalizedTexts() as $text) {
            $result[$text->getLocale()] = $text;
        }
        return $result;
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

    public function getPublicChildGroups(): ReadableCollection
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

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
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

    public function getPointsLimit(): ?int
    {
        return $this->pointsLimit;
    }

    public function setPointsLimit(?int $pointsLimit): void
    {
        $this->pointsLimit = $pointsLimit;
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

    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function isOrganizational(): bool
    {
        return $this->isOrganizational;
    }

    public function setOrganizational(bool $value = true): void
    {
        $this->isOrganizational = $value;
    }

    public function isDetaining(): bool
    {
        return $this->isDetaining;
    }

    public function setDetaining(bool $value = true): void
    {
        $this->isDetaining = $value;
    }

    public function isExam(): bool
    {
        return $this->isExam;
    }

    public function setExam(bool $value = true): void
    {
        $this->isExam = $value;
    }

    public function getExamBegin(): ?DateTime
    {
        return $this->examBegin;
    }

    public function getExamEnd(): ?DateTime
    {
        return $this->examEnd;
    }

    public function getExams(): Collection
    {
        return $this->exams;
    }

    public function getExternalAttributes(): Collection
    {
        return $this->externalAttributes;
    }
}
