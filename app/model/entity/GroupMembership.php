<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidMembershipException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"user_id", "group_id", "inherited_from_id"})})
 */
class GroupMembership implements JsonSerializable
{
    public const TYPE_ADMIN = "admin";
    public const TYPE_SUPERVISOR = "supervisor";
    public const TYPE_OBSERVER = "observer";
    public const TYPE_STUDENT = "student";

    // all declared types
    public const KNOWN_TYPES = [
        self::TYPE_ADMIN,
        self::TYPE_SUPERVISOR,
        self::TYPE_OBSERVER,
        self::TYPE_STUDENT,
    ];

    // membership types that are inherited from parent groups
    // in the order of priorities (e.g., admin must be first as it supress other relations)
    public const INHERITABLE_TYPES = [
        self::TYPE_ADMIN,
    ];

    public const TYPE_ALL = "*";

    public function __construct(Group $group, User $user, string $type, Group $inheritedFrom = null)
    {
        $this->group = $group;
        $this->user = $user;
        $this->setType($type);
        $this->createdAt = new DateTime();
        $this->inheritedFrom = $inheritedFrom;
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="memberships")
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="memberships")
     */
    protected $group;

    /**
     * @ORM\Column(type="string")
     */
    protected $type;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="Group")
     * When not null, it indicates the membership was inherited from ancestral group (id of which it holds).
     * Inheritance applies only for selected types of memberships (e.g., admin).
     * At present, explicit inherited memberships are used to capture inherited admin privileges
     * which are in place at the moment when a sub-groups are being placed to archive.
     * In the futue, this technique may be used for performance optimizations as well.
     */
    protected $inheritedFrom = null;

    public function setType(string $type)
    {
        if (!in_array($type, self::KNOWN_TYPES)) {
            throw new InvalidMembershipException("Unsuported membership type '$type'");
        }

        if ($this->type !== $type) {
            $this->type = $type;
            $this->createdAt = new DateTime();
        }
    }

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "userId" => $this->user->getId(),
            "groupId" => $this->group->getId(),
            "type" => $this->type,
            "createdAt" => $this->createdAt->getTimestamp(),
            "inheritedFrom" => $this->inheritedFrom ? $this->inheritedFrom->getId() : null,
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isInherited(): bool
    {
        return $this->inheritedFrom !== null;
    }

    public function getInheritedFrom(): ?Group
    {
        return $this->inheritedFrom;
    }
}
