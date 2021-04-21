<?php

namespace App\Security\ACL;

use App\Model\Entity\ShadowAssignment;

interface IShadowAssignmentPermissions
{
    public function canViewDetail(ShadowAssignment $assignment): bool;

    public function canUpdate(ShadowAssignment $assignment): bool;

    public function canRemove(ShadowAssignment $assignment): bool;

    public function canViewAllPoints(ShadowAssignment $assignment): bool;

    public function canCreatePoints(ShadowAssignment $assignment): bool;

    public function canUpdatePoints(ShadowAssignment $assignment): bool;

    public function canRemovePoints(ShadowAssignment $assignment): bool;
}
