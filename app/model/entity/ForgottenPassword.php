<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class ForgottenPassword
{
    public function __construct(
        User $user,
        string $sentTo,
        string $IPaddress
    ) {
        $this->user = $user;
        $this->sentTo = $sentTo;
        $this->requestedAt = new DateTime();
        $this->IPaddress = $IPaddress;
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
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;

    public function getUser(): ?User
    {
        return $this->user->isDeleted() ? null : $this->user;
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $requestedAt;

    /**
     * @ORM\Column(type="string")
     */
    protected $sentTo;

    /**
     * @ORM\Column(type="string")
     */
    protected $IPaddress;

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getRequestedAt(): DateTime
    {
        return $this->requestedAt;
    }

    public function getSentTo(): string
    {
        return $this->sentTo;
    }

    public function getIPaddress(): string
    {
        return $this->IPaddress;
    }
}
