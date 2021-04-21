<?php

namespace App\Security\ACL;

use App\Model\Entity\Assignment;
use App\Model\Entity\User;

interface IAssignmentPermissions
{
    public function canViewAll(): bool;

    public function canViewDetail(Assignment $assignment): bool;

    public function canViewDescription(Assignment $assignment): bool;

    public function canUpdate(Assignment $assignment): bool;

    public function canRemove(Assignment $assignment): bool;

    public function canSubmit(Assignment $assignment, User $student): bool;

    public function canViewSubmissions(Assignment $assignment, User $student): bool;

    public function canResubmitSubmissions(Assignment $assignment): bool;

    public function canViewAssignmentSolutions(Assignment $assignment): bool;
}
