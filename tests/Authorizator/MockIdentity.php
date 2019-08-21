<?php

class MockIdentity extends App\Security\Identity
{
  private $roles;
  private $effectiveRoles;

  public function __construct(array $roles, array $effectiveRoles = [])
  {
    parent::__construct(null, null);
    $this->roles = $roles;
    $this->effectiveRoles = $effectiveRoles;
  }

  public function getRoles()
  {
    return $this->roles;
  }

  function getScopeRoles()
  {
    return $this->effectiveRoles;
  }
}
