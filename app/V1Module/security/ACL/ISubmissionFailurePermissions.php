<?php

namespace App\Security\ACL;

use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\SubmissionFailure;

interface ISubmissionFailurePermissions
{
    public function canViewAll(): bool;

    public function canView(SubmissionFailure $failure): bool;

    public function canResolve(SubmissionFailure $failure): bool;

    public function canViewForAssignmentSolutionSubmission(AssignmentSolutionSubmission $submission): bool;
}
