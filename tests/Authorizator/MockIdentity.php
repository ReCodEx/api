<?php

class MockIdentity extends App\Security\Identity
{
  private $roles;

  public function __construct(array $roles)
  {
    parent::__construct(null, null);
    $this->roles = $roles;
  }

  public function getRoles()
  {
    return $this->roles;
  }
}
