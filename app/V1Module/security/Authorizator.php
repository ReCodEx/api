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

  protected $roles = [];

  private $initialized = false;

  public function __construct(PolicyRegistry $policy) {
    $this->policy = $policy;
  }

  protected abstract function checkPermissions(string $role, string $resource, string $privilege): bool;

  protected abstract function setup();

  public function isAllowed(Identity $identity, string $resource, string $privilege, array $context): bool {
    if (!$this->initialized) {
      $this->setup();
    }

    $this->queriedIdentity = $identity;
    $this->queriedContext = $context;

    return $this->checkPermissions($identity->getRoles()[0], $resource, $privilege);
  }

  protected function addRole($role, $parents) {
    $this->roles[$role] = $parents;
  }

  protected function isInRole($target, $role): bool {
    if ($target === $role) {
      return true;
    }

    foreach ($this->roles[$target] as $parent) {
      if ($this->isInRole($parent, $role)) {
        return true;
      }
    }

    return false;
  }
}
