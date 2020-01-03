<?php

namespace App\Security\ACL;

interface IEmailPermissions
{
    function canSendToAll(): bool;

    function canSendToSupervisors(): bool;

    function canSendToRegularUsers(): bool;
}
