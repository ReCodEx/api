<?php

namespace App\Security\Policies;

interface IPermissionPolicy
{
    public function getAssociatedClass();
}
