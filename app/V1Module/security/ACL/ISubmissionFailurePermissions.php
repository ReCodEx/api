<?php
namespace App\Security\ACL;


use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\SubmissionFailure;

interface ISubmissionFailurePermissions {
  function canViewAll(): bool;
  function canView(SubmissionFailure $failure): bool;
  function canResolve(SubmissionFailure $failure): bool;
  function canViewForSubmission(AssignmentSolutionSubmission $submission): bool;
}
