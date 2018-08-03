<?php
namespace App\Security\ACL;

use App\Model\Entity\ShadowAssignment;

interface IShadowAssignmentPermissions {
  function canViewDetail(ShadowAssignment $assignment): bool;
  function canUpdate(ShadowAssignment $assignment): bool;
  function canRemove(ShadowAssignment $assignment): bool;
  function canViewPointsList(ShadowAssignment $assignment): bool;
  function canCreatePoints(ShadowAssignment $assignment): bool;
}
