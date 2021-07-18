<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidMembershipException;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class GroupMembership implements JsonSerializable
{
    const STATUS_REQUESTED = "requested";
    const STATUS_ACTIVE = "active";
    const STATUS_REJECTED = "rejected";

    const TYPE_STUDENT = "student";
    const TYPE_SUPERVISOR = "supervisor";
    const TYPE_ALL = "*";

    public function __construct(Group $group, User $user, string $type, string $status)
    {
        $this->group = $group;
        $this->user = $user;
        $this->setType($type);
        $this->setStatus($status);
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
    protected $status;

    public function setStatus(string $status)
    {
        if ($this->status === $status) {
            return; // nothing changes
        }

        switch ($status) {
            case self::STATUS_REQUESTED:
                $this->requestedAt = new \DateTime();
                break;
            case self::STATUS_ACTIVE:
                $this->joinedAt = new \DateTime();
                break;
            case self::STATUS_REJECTED:
                $this->rejectedAt = new \DateTime();
                break;
            default:
                throw new InvalidMembershipException("Unsupported membership status '$status'");
        }
        $this->status = $status;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $type;

    public function setType(string $type)
    {
        if ($this->type === $type) {
            return; // nothing changes
        }

        switch ($type) {
            case self::TYPE_STUDENT:
                $this->studentSince = new \DateTime();
                break;
            case self::TYPE_SUPERVISOR:
                $this->supervisorSince = new \DateTime();
                break;
            default:
                throw new InvalidMembershipException("Unsuported membership type '$type'");
        }
        $this->type = $type;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $requestedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $joinedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $rejectedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $studentSince;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $supervisorSince;

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "userId" => $this->user->getId(),
            "groupId" => $this->group->getId(),
            "status" => $this->status,
            "requestedAt" => $this->requestedAt ? $this->requestedAt->getTimestamp() : null,
            "joinedAt" => $this->joinedAt ? $this->joinedAt->getTimestamp() : null,
            "rejectedAt" => $this->rejectedAt ? $this->rejectedAt->getTimestamp() : null,
            "studentSince" => $this->studentSince ? $this->studentSince->getTimestamp() : null,
            "supervisorSince" => $this->supervisorSince ? $this->supervisorSince->getTimestamp() : null,
        ];
    }

    ////////////////////////////////////////////////////////////////////////////

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRejectedAt(): ?DateTime
    {
        return $this->rejectedAt;
    }

    public function getJoinedAt(): ?DateTime
    {
        return $this->joinedAt;
    }

    public function getRequestedAt(): ?DateTime
    {
        return $this->requestedAt;
    }

    public function getStudentSince(): ?DateTime
    {
        return $this->studentSince;
    }

    public function getSupervisorSince(): ?DateTime
    {
        return $this->supervisorSince;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
