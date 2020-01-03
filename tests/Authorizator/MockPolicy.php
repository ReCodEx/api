<?php

use App\Security\Identity;

class MockPolicy implements \App\Security\Policies\IPermissionPolicy
{
    public function condition1(Identity $identity, $resource = null)
    {
        return false;
    }

    public function condition2(Identity $identity, $resource = null)
    {
        return false;
    }

    public function condition3(Identity $identity, $resource = null)
    {
        return false;
    }

    function getAssociatedClass()
    {
        return null;
    }
}
