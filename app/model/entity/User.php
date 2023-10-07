<?php

namespace App\Model\Entity;

use App\Security\Roles;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gravatar\Gravatar;
use App\Exceptions\ApiException;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class User
{
    use CreateableEntity;
    use DeleteableEntity;

    public function __construct(
        string $email,
        string $firstName,
        string $lastName,
        string $titlesBeforeName,
        string $titlesAfterName,
        ?string $role,
        Instance $instance,
        bool $instanceAdmin = false
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->titlesBeforeName = $titlesBeforeName;
        $this->titlesAfterName = $titlesAfterName;
        $this->email = $email;
        $this->isVerified = false;
        $this->isAllowed = true;
        $this->memberships = new ArrayCollection();
        $this->exercises = new ArrayCollection();
        $this->createdAt = new DateTime();
        $this->instances = new ArrayCollection([$instance]);
        $instance->addMember($this);
        $this->settings = new UserSettings("en");
        $this->login = null;
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
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $titlesBeforeName;

    /**
     * @ORM\Column(type="string")
     */
    protected $firstName;

    /**
     * @ORM\Column(type="string")
     */
    protected $lastName;

    public function getName()
    {
        return trim("{$this->titlesBeforeName} {$this->firstName} {$this->lastName} {$this->titlesAfterName}");
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $titlesAfterName;

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
    public function setGravatar(bool $useGravatar = true)
    {
        $this->avatarUrl = !$useGravatar ? null :
            Gravatar::image($this->email, 200, "retro", "g", "png", false)->url();
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isVerified;

    public function isVerified()
    {
        return $this->isVerified;
    }

    public function setVerified($verified = true)
    {
        $this->isVerified = $verified;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isAllowed;

    public function isAllowed()
    {
        return $this->isAllowed;
    }

    /**
     * @ORM\ManyToMany(targetEntity="Instance", inversedBy="members")
     */
    protected $instances;

    public function belongsTo(Instance $instance)
    {
        return $this->instances->contains($instance);
    }

    public function getInstancesIds()
    {
        return $this->instances->map(
            function (Instance $instance) {
                return $instance->getId();
            }
        )->getValues();
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

    protected function findMembership(Group $group, string $type)
    {
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

    public function findMembershipAsStudent(Group $group)
    {
        return $this->findMembership($group, GroupMembership::TYPE_STUDENT);
    }

    public function findMembershipAsSupervisor(Group $group)
    {
        return $this->findMembership($group, GroupMembership::TYPE_SUPERVISOR);
    }

    protected function getMemberships()
    {
        return $this->memberships->filter(
            function (GroupMembership $membership) {
                return $membership->getGroup()->getDeletedAt() === null;
            }
        );
    }

    /**
     * Returns array with all groups in which this user has given type.
     * @param string $type
     * @return ArrayCollection
     */
    protected function findGroupMemberships($type)
    {
        $filter = Criteria::create()
            ->where(Criteria::expr()->eq("type", $type));
        return $this->getMemberships()->matching($filter)->getValues();
    }

    public function findGroupMembershipsAsSupervisor()
    {
        return $this->findGroupMemberships(GroupMembership::TYPE_SUPERVISOR);
    }

    public function findGroupMembershipsAsStudent()
    {
        return $this->findGroupMemberships(GroupMembership::TYPE_STUDENT);
    }

    protected function addMembership(Group $group, string $type)
    {
        $membership = new GroupMembership($group, $this, $type);
        $this->memberships->add($membership);
        $group->addMembership($membership);
    }

    protected function makeMemberOf(Group $group, string $type)
    {
        $membership = $this->findMembership($group, $type);
        if ($membership === null) {
            $this->addMembership($group, $type);
        } else {
            $membership->setType($type);
        }
    }

    /**
     * Get list of groups associated with the user by membership relation.
     * @param string|null $type filter that selects only groups by defined type of membership
     * @param string|null $notType filter that excludes groups by defined type of membership
     */
    public function getGroups(string $type = null, string $notType = null)
    {
        $result = $this->getMemberships();

        if ($type !== null) {
            $filter = Criteria::create()->where(Criteria::expr()->eq("type", $type));
            $result = $result->matching($filter);
        }
        if ($notType !== null) {
            $filter = Criteria::create()->where(Criteria::expr()->neq("type", $notType));
            $result = $result->matching($filter);
        }

        return $result->map(
            function (GroupMembership $membership) {
                return $membership->getGroup();
            }
        );
    }

    public function getGroupsAsStudent()
    {
        return $this->getGroups(GroupMembership::TYPE_STUDENT);
    }

    public function makeStudentOf(Group $group)
    {
        $this->makeMemberOf($group, GroupMembership::TYPE_STUDENT);
    }

    public function getGroupsAsSupervisor()
    {
        return $this->getGroups(GroupMembership::TYPE_SUPERVISOR);
    }

    public function makeSupervisorOf(Group $group)
    {
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
     * @ORM\OneToOne(targetEntity="Login", mappedBy="user", cascade={"all"})
     */
    protected $login;


    /**
     * @return array
     */
    public function getNameParts(): array
    {
        return [
            "titlesBeforeName" => $this->titlesBeforeName,
            "firstName" => $this->firstName,
            "lastName" => $this->lastName,
            "titlesAfterName" => $this->titlesAfterName,
        ];
    }

    /**
     * Returns true if the user entity is associated with a local login entity.
     * @return bool
     */
    public function hasLocalAccount(): bool
    {
        return $this->login !== null;
    }

    /**
     * Returns true if the user entity is associated with a external login entity.
     * @return bool
     */
    public function hasExternalAccounts(): bool
    {
        return !$this->externalLogins->isEmpty();
    }

    /**
     * Return an associative array [ service => externalId ] for the user.
     * If there are multiple IDs for the same service, they are concatenated in an array.
     * If a filter is provided, only services specified on the filter list are yielded.
     * @param array|null $filter A list of services to be included in the result. Null = all services.
     * @return array
     */
    public function getConsolidatedExternalLogins(?array $filter = null)
    {
        if ($filter === []) {
            return [];  // why should we bother...
        }

        // assemble the result structure [ service => ids ]
        $res = [];
        foreach ($this->externalLogins as $externalLogin) {
            if (empty($res[$externalLogin->getAuthService()])) {
                $res[$externalLogin->getAuthService()] = [];
            }
            $res[$externalLogin->getAuthService()][] = $externalLogin->getExternalId();
        }

        // single IDs (per service) are turned into scalars
        foreach ($res as &$externalIds) {
            if (count($externalIds) === 1) {
                $externalIds = reset($externalIds);
            }
        }
        unset($externalIds);  // make sure this reference is not accidentaly reused

        // filter the list if necessary
        if ($filter !== null) {
            $resFiltered = [];
            foreach ($filter as $service) {
                if (!empty($res[$service])) {
                    $resFiltered[$service] = $res[$service];
                }
            }
            return $resFiltered;
        }

        return $res;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     * When the last authentication or token renewal occurred.
     */
    protected $lastAuthenticationAt = null;

    /**
     * Update the last authentication time to present.
     * @param DateTime|null $time the authentication time (if null, current time is set)
     */
    public function updateLastAuthenticationAt(DateTime $time = null)
    {
        $this->lastAuthenticationAt = $time ?? new DateTime();
    }

    /**
     * @ORM\OneToOne(targetEntity="UserUiData", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $uiData = null;

    /**
     * @return UserUiData|null
     */
    public function getUiData(): ?UserUiData
    {
        return $this->uiData;
    }

    /**
     * @param UserUiData|null $uiData
     */
    public function setUiData(?UserUiData $uiData)
    {
        $this->uiData = $uiData;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getInstances(): Collection
    {
        return $this->instances;
    }

    public function getExercises(): Collection
    {
        return $this->exercises;
    }

    public function getSettings(): UserSettings
    {
        return $this->settings;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function setTitlesBeforeName(string $titlesBeforeName): void
    {
        $this->titlesBeforeName = $titlesBeforeName;
    }

    public function setTitlesAfterName(string $titlesAfterName): void
    {
        $this->titlesAfterName = $titlesAfterName;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function setIsAllowed(bool $isAllowed): void
    {
        $this->isAllowed = $isAllowed;
    }

    public function getLogin(): ?Login
    {
        return $this->login;
    }

    public function setLogin(?Login $login): void
    {
        $this->login = $login;
    }

    public function getExternalLogins(): Collection
    {
        return $this->externalLogins;
    }

    public function getTokenValidityThreshold(): ?DateTime
    {
        return $this->tokenValidityThreshold;
    }

    public function setTokenValidityThreshold(DateTime $tokenValidityThreshold): void
    {
        $this->tokenValidityThreshold = $tokenValidityThreshold;
    }

    public function getLastAuthenticationAt(): ?DateTime
    {
        return $this->lastAuthenticationAt;
    }
}
