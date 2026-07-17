<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

#[ORM\Table]
#[ORM\Index(name: 'grouped_created_at_idx', columns: ['group_id', 'created_at'])]
#[ORM\Entity]
class GroupInvitation implements JsonSerializable
{
    use CreatableEntity;

    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    /**
     * @var DateTime
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $expireAt = null;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'invitations')]
    protected $group;

    /**
     * who created the invitation
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $host;

    /**
     * short remark for those who are about to use the link
     */
    #[ORM\Column(type: 'string')]
    protected $note;

    /**
     * Constructor
     * @param Group $group
     * @param User $host
     * @param DateTime|null $expireAt
     * @param string $note
     */
    public function __construct(Group $group, User $host, ?DateTime $expireAt = null, string $note = '')
    {
        $this->group = $group;
        $this->host = $host;
        $this->expireAt = $expireAt;
        $this->note = $note;
        $this->createdNow();
    }

    public function jsonSerialize(): mixed
    {
        $group = $this->getGroup();
        $host = $this->getHost();
        return [
            "id" => $this->getId(),
            "groupId" => $group ? $group->getId() : null,
            "hostId" => $host ? $host->getId() : null,
            "createdAt" => $this->getCreatedAt()->getTimestamp(),
            "expireAt" => $this->getExpireAt() ? $this->getExpireAt()->getTimestamp() : null,
            "note" => $this->getNote(),
        ];
    }


    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getExpireAt(): ?DateTime
    {
        return $this->expireAt;
    }

    public function hasExpired(): bool
    {
        return $this->expireAt !== null && $this->expireAt->getTimestamp() < time();
    }

    public function setExpireAt(?DateTime $expireAt): void
    {
        $this->expireAt = $expireAt;
    }

    public function getHost(): ?User
    {
        return $this->host->isDeleted() ? null : $this->host;
    }

    public function getGroup(): ?Group
    {
        return $this->group->isDeleted() ? null : $this->group;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }
}
