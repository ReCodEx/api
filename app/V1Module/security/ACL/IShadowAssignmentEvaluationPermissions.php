<?php
namespace App\Security\ACL;

use App\Model\Entity\ShadowAssignmentEvaluation;

interface IShadowAssignmentEvaluationPermissions {
  function canViewDetail(ShadowAssignmentEvaluation $evaluation): bool;
  function canUpdate(ShadowAssignmentEvaluation $evaluation): bool;
  function canRemove(ShadowAssignmentEvaluation $evaluation): bool;
}
