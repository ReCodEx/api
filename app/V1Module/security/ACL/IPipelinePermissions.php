<?php

namespace App\Security\ACL;

use App\Model\Entity\Exercise;
use App\Model\Entity\Pipeline;

interface IPipelinePermissions
{
    public function canViewAll(): bool;

    public function canViewDetail(Pipeline $pipeline): bool;

    public function canUpdate(Pipeline $pipeline): bool;

    public function canCreate(): bool;

    public function canRemove(Pipeline $pipeline): bool;

    public function canFork(Pipeline $pipeline): bool;
}
