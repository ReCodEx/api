<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\UniqueConstraint(columns: ['auth_service', 'user_id'])]
#[ORM\UniqueConstraint(columns: ['auth_service', 'external_id'])]
#[ORM\Entity]
class ExternalLogin
{
    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    #[ORM\Column(type: 'string', length: 32)]
    protected $authService;

    #[ORM\Column(type: 'string', length: 128)]
    protected $externalId;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'externalLogins')]
    protected $user;

    public function __construct(User $user, string $authService, string $externalId)
    {
        $this->user = $user;
        $this->authService = $authService;
        $this->externalId = $externalId;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getAuthService(): string
    {
        return $this->authService;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
