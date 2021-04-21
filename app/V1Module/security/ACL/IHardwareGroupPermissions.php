<?php

namespace App\Security\ACL;

interface IHardwareGroupPermissions
{
    public function canViewAll(): bool;
}
