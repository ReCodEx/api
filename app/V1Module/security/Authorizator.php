<?php

namespace App\Security;

use Nette\Security as NS;

abstract class Authorizator implements IAuthorizator {
  /** @var NS\Permission */
  protected $acl;

  /** @var Identity */
  protected $queriedIdentity;

  /** @var string[] */
  protected $queriedContext;

  /** @var PolicyRegistry */
  protected $policy;

  /** @var Roles */
  protected $roles;

  private $initialized = false;

  public function __construct(PolicyRegistry $policy, Roles $roles) {
    $this->policy = $policy;
    $this->roles = $roles;
  }

  protected abstract function checkPermissions(string $role, string $resource, string $privilege): bool;

  public function isAllowed(Identity $identity, string $resource, string $privilege, array $context): bool {
    $this->queriedIdentity = $identity;
    $this->queriedContext = $context;

    $effectiveRoles = $identity->getEffectiveRoles();
    return $this->checkPermissionsForRoleList($identity->getRoles(), $resource, $privilege)
      && ($effectiveRoles === [] || $this->checkPermissionsForRoleList($effectiveRoles, $resource, $privilege));
  }

  protected function checkPermissionsForRoleList($roleList, $resource, $privilege): bool {
    foreach ($roleList as $role) {
      if ($this->checkPermissions($role, $resource, $privilege)) {
        return true;
      }
    }

    return false;
  }

  protected function isInRole(string $actualTestedRole, string $minimalRequestedRole): bool {
    return $this->roles->isInRole($actualTestedRole, $minimalRequestedRole);
  }
}
