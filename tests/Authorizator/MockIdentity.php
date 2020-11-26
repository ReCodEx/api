<?php

class MockIdentity extends App\Security\Identity
{
    private $roles;
    private $scopeRoles;
    private $effectiveRole;

    public function __construct(array $roles, array $scopeRoles = [], string $effectiveRole = null)
    {
        parent::__construct(null, null);
        $this->roles = $roles;
        $this->scopeRoles = $scopeRoles;
        $this->effectiveRole = $effectiveRole;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    function getScopeRoles()
    {
        return $this->scopeRoles;
    }

    function getEffectiveRole(): ?string
    {
        return $this->effectiveRole;
    }
}
