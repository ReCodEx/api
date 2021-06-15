<?php

namespace App\Security\ACL;

interface IEmailPermissions
{
    public function canSendToAll(): bool;

    public function canSendToSupervisors(): bool;

    public function canSendToRegularUsers(): bool;
}
