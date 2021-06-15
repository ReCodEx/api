<?php

namespace App\Security\ACL;

interface IRuntimeEnvironmentPermissions
{
    public function canViewAll(): bool;
}
