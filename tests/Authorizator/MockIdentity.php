<?php

class MockIdentity extends App\Security\Identity
{
  private $roles;

  public function __construct(array $roles)
  {
    parent::__construct(NULL, NULL);
    $this->roles = $roles;
  }

  public function getRoles()
  {
    return $this->roles;
  }
}