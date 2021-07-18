<?php

namespace App\Model\Entity;

use \DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class ForgottenPassword
{
    public function __construct(
        User $user,
        string $sentTo,
        string $redirectUrl,
        string $IPaddress
    ) {
        $this->user = $user;
        $this->sentTo = $sentTo;
        $this->requestedAt = new DateTime();
        $this->redirectUrl = $redirectUrl;
        $this->IPaddress = $IPaddress;
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
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
    protected $redirectUrl;

    /**
     * @ORM\Column(type="string")
     */
    protected $IPaddress;

    ////////////////////////////////////////////////////////////////////////////

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRequestedAt(): DateTime
    {
        return $this->requestedAt;
    }

    public function getSentTo(): string
    {
        return $this->sentTo;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getIPaddress(): string
    {
        return $this->IPaddress;
    }
}
