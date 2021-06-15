<?php

namespace App\Security\ACL;

interface IBrokerPermissions
{
    public function canViewStats(): bool;

    public function canFreeze(): bool;

    public function canUnfreeze(): bool;
}
