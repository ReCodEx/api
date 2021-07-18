<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Exceptions\InvalidArgumentException;
use Nette\Utils\Validators;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"auth_service", "external_id"})})
 */
class ExternalLogin
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    protected $authService;

    /**
     * @ORM\Column(type="string", length=128)
     */
    protected $externalId;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="externalLogins")
     */
    protected $user;

    public function __construct(User $user, string $authService, string $externalId)
    {
        $this->user = $user;
        $this->authService = $authService;
        $this->externalId = $externalId;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function getId()
    {
        return $this->id;
    }

    public function getAuthService(): string
    {
        return $this->authService;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
