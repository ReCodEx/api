<?php

namespace App\Security\ACL;

use App\Model\Entity\AsyncJob;

interface IAsyncJobPermissions
{
    public function canViewDetail(AsyncJob $asyncJob): bool;

    public function canList(): bool;

    public function canAbort(AsyncJob $asyncJob): bool;

    public function canPing(): bool;
}
