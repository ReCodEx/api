<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 * Entity holding information about iCal provider for user events.
 */
class UserCalendar implements JsonSerializable
{
    use CreatableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=32)
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime|null
     */
    protected $expiredAt = null;

    /**
     * @return string base64-encoded ID (32 chars, 192 bits of information), '+/=' replaced with URL-safe chars
     */
    public static function generateId(): string
    {
        $key = random_bytes(24); // 192 bits of random data
        $encoded = str_replace(['+', '/', '='], ['-', '~', '.'], base64_encode($key));
        return substr($encoded, 0, 32); // encoded in 192 / 6 = 32 chars
    }

    /**
     * Initialize new user calendar record (ID is generated automatically)
     * @param User $user to whom the calendar is attached to
     */
    public function __construct(User $user)
    {
        $this->id = self::generateId();
        $this->user = $user;
        $this->createdAt = new DateTime();
    }

    /**
     * Enable automatic serialization to JSON
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'userId' => $this->getUser() ? $this->getUser()->getId() : null,
            'createdAt' => $this->getCreatedAt()->getTimestamp(),
            'expiredAt' => $this->getExpiredAt() ? $this->getExpiredAt()->getTimestamp() : null,
        ];
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user->isDeleted() ? null : $this->user;
    }

    public function getExpiredAt(): ?DateTime
    {
        return $this->expiredAt;
    }

    public function isExpired(): bool
    {
        return $this->expiredAt && $this->expiredAt->getTimestamp() <= (new DateTime())->getTimestamp();
    }

    public function setExpiredAt($date = new DateTime()): void
    {
        $this->expiredAt = $date;
    }
}
