<?php

namespace App\Security;

abstract class Authorizator implements IAuthorizator
{
    /** @var Identity */
    protected $queriedIdentity;

    /** @var string[] */
    protected $queriedContext;

    /** @var PolicyRegistry */
    protected $policy;

    /** @var Roles */
    protected $roles;


    public function __construct(PolicyRegistry $policy, Roles $roles)
    {
        $this->policy = $policy;
        $this->roles = $roles;
    }

    abstract protected function checkPermissions(string $role, string $resource, string $privilege): bool;

    public function isAllowed(Identity $identity, string $resource, string $privilege, array $context): bool
    {
        $this->queriedIdentity = $identity;
        $this->queriedContext = $context;

        $scopeRoles = $identity->getScopeRoles();
        $effectiveRole = $identity->getEffectiveRole();
        return $this->checkPermissionsForRoleList($identity->getRoles(), $resource, $privilege)
            && ($scopeRoles === [] || $this->checkPermissionsForRoleList($scopeRoles, $resource, $privilege))
            && ($effectiveRole === null || $this->checkPermissions($effectiveRole, $resource, $privilege));
    }

    protected function checkPermissionsForRoleList($roleList, $resource, $privilege): bool
    {
        foreach ($roleList as $role) {
            if ($this->checkPermissions($role, $resource, $privilege)) {
                return true;
            }
        }

        return false;
    }

    protected function isInRole(string $actualTestedRole, string $minimalRequestedRole): bool
    {
        return $this->roles->isInRole($actualTestedRole, $minimalRequestedRole);
    }
}
