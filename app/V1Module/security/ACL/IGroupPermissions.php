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

    public function canViewSubgroups(Group $group): bool;

    public function canViewStudents(Group $group): bool;

    public function canViewSupervisors(Group $group): bool;

    public function canViewAdmin(Group $group): bool;

    public function canSetAdmin(Group $group): bool;

    public function canAddStudent(Group $group, User $student): bool;

    public function canRemoveStudent(Group $group, User $student): bool;

    public function canAddSupervisor(Group $group, User $supervisor): bool;

    public function canRemoveSupervisor(Group $group, User $supervisor): bool;

    public function canViewStats(Group $group): bool;

    public function canViewStudentStats(Group $group, User $student): bool;

    public function canAddSubgroup(Group $group): bool;

    public function canUpdate(Group $group): bool;

    public function canRemove(Group $group): bool;

    public function canArchive(Group $group): bool;

    public function canRelocate(Group $group): bool;

    public function canViewExercises(Group $group): bool;

    public function canAssignExercise(Group $group, Exercise $exercise): bool;

    public function canCreateExercise(Group $group): bool;

    public function canCreateShadowAssignment(Group $group): bool;

    public function canViewPublicDetail(Group $group): bool;

    public function canAddStudentToArchivedGroup($group, $user): bool;

    public function canBecomeSupervisor(Group $group): bool;

    public function canSendEmail(Group $group): bool;
}
