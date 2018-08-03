<?php
namespace App\Security\ACL;

use App\Model\Entity\ShadowAssignmentPoints;

interface IShadowAssignmentPointsPermissions {
  function canViewDetail(ShadowAssignmentPoints $assignmentPoints): bool;
  function canUpdate(ShadowAssignmentPoints $assignmentPoints): bool;
  function canRemove(ShadowAssignmentPoints $assignmentPoints): bool;
}
