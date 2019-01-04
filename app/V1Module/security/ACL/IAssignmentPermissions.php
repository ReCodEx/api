<?php
namespace App\Security\ACL;

use App\Model\Entity\Assignment;
use App\Model\Entity\User;

interface IAssignmentPermissions {
  function canViewAll(): bool;
  function canViewDetail(Assignment $assignment): bool;
  function canViewDescription(Assignment $assignment): bool;
  function canUpdate(Assignment $assignment): bool;
  function canRemove(Assignment $assignment): bool;
  function canSubmit(Assignment $assignment, User $student): bool;
  function canViewSubmissions(Assignment $assignment, User $student): bool;
  function canResubmitSubmissions(Assignment $assignment): bool;
  function canViewAssignmentSolutions(Assignment $assignment): bool;
}
