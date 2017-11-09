<?php
namespace App\Security\ACL;


use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\SubmissionFailure;

interface ISubmissionFailurePermissions {
  function canViewAll(): bool;
  function canView(SubmissionFailure $failure): bool;
  function canResolve(SubmissionFailure $failure): bool;
  function canViewForSubmission(AssignmentSolution $submission): bool;
}
