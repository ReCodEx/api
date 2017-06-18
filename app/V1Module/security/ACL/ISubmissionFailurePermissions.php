<?php
namespace App\Security\ACL;


use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionFailure;

interface ISubmissionFailurePermissions {
  function canViewAll(): bool;
  function canView(SubmissionFailure $failure): bool;
  function canResolve(SubmissionFailure $failure): bool;
  function canViewForSubmission(Submission $submission): bool;
}