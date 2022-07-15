<?php

namespace App\Security;

use App\Model\Entity\User;
use Nette;

class Identity implements Nette\Security\IIdentity
{
    use Nette\SmartObject;

    /** @var ?User */
    private $user;

    /** @var ?AccessToken */
    private $token;

    public const UNAUTHENTICATED_ROLE = "unauthenticated";

    public function __construct(?User $user, ?AccessToken $token)
    {
        $this->user = $user;
        $this->token = $token;
    }

    /**
     * Returns the ID of user.
     * @return mixed
     */
    public function getId()
    {
        return $this->user ? $this->user->getId() : null;
    }

    /**
     * Returns a list of roles that the user is a member of.
     * @return array
     */
    public function getRoles(): array
    {
        return $this->user ? [$this->user->getRole()] : [self::UNAUTHENTICATED_ROLE];
    }

    /**
     * Returns a list of scope roles that the user is a member of (in current session).
     * @return array
     */
    public function getScopeRoles()
    {
        if (!$this->token) {
            return [];
        }

        return array_map(
            function (string $role) {
                return "scope-" . $role;
            },
            $this->token->getScopes()
        );
    }

    /**
     * Return effective role that user uses in current session.
     * @return string|null
     */
    public function getEffectiveRole(): ?string
    {
        if (!$this->token) {
            return null;
        }

        return $this->token->getEffectiveRole();
    }

    public function getUserData()
    {
        return $this->user;
    }

    public function getData()
    {
        $this->getUserData();
    }

    public function getToken()
    {
        return $this->token;
    }

    public function isInScope($scope)
    {
        return $this->token ? $this->token->isInScope($scope) : false;
    }
}
