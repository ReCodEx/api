<?php

use App\Security\Identity;

class MockPolicy implements \App\Security\Policies\IPermissionPolicy
{
  function getByID($id)
  {
    return NULL;
  }

  public function condition1(Identity $identity, $resource = NULL)
  {
    return FALSE;
  }

  public function condition2(Identity $identity, $resource = NULL)
  {
    return FALSE;
  }

  public function condition3(Identity $identity, $resource = NULL)
  {
    return FALSE;
  }
}