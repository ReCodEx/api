<?php

namespace App\Security\ACL;

use App\Model\Entity\PlagiarismDetectionBatch;

interface IPlagiarismPermissions
{
    public function canViewBatches(): bool;

    public function canCreateBatch(): bool;

    public function canUpdateBatch(PlagiarismDetectionBatch $batch): bool;
}
