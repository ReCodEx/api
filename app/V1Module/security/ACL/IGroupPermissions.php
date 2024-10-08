<?php

namespace App\Security\ACL;

use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\User;

interface IGroupPermissions
{
    public function canViewAll(): bool;

    public function canViewAssignments(Group $group): bool;

    public function canViewDetail(Group $group): bool;

    public function canViewStudents(Group $group): bool;

    public function canAddMember(Group $group, User $supervisor): bool;

    public function canAddStudent(Group $group, User $student): bool;

    public function canInviteStudents(Group $group): bool;

    public function canRemoveMember(Group $group, User $supervisor): bool;

    public function canRemoveStudent(Group $group, User $student): bool;

    public function canViewStats(Group $group): bool;

    public function canViewStudentStats(Group $group, User $student): bool;

    public function canAddSubgroup(Group $group): bool;

    public function canUpdate(Group $group): bool;

    public function canRemove(Group $group): bool;

    public function canSetOrganizational(Group $group): bool;

    public function canArchive(Group $group): bool;

    public function canSetExamFlag(Group $group): bool;

    public function canSetExamPeriod(Group $group): bool;

    public function canRemoveExamPeriod(Group $group): bool;

    public function canViewExamLocks(Group $group): bool;

    public function canViewExamLocksIPs(Group $group): bool;

    public function canRelocate(Group $group): bool;

    public function canAssignExercise(Group $group): bool;

    public function canCreateExercise(Group $group): bool;

    public function canCreateShadowAssignment(Group $group): bool;

    public function canViewPublicDetail(Group $group): bool;

    public function canAddStudentToArchivedGroup($group, $user): bool;

    public function canBecomeMember(Group $group): bool;

    public function canSendEmail(Group $group): bool;

    public function canViewInvitations(Group $group): bool;

    public function canAcceptInvitation(Group $group): bool;

    public function canEditInvitations(Group $group): bool;

    public function canLockStudent(Group $group, User $student): bool;

    public function canUnlockStudent(Group $group, User $student): bool;

    public function canViewExternalAttributes(): bool;

    public function canSetExternalAttributes(): bool;
}
