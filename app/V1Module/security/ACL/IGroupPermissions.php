<?php
namespace App\Security\ACL;


use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\User;

interface IGroupPermissions
{
  function canViewAll(): bool;
  function canViewAssignments(Group $group): bool;
  function canViewDetail(Group $group): bool;
  function canViewSubgroups(Group $group): bool;
  function canViewStudents(Group $group): bool;
  function canViewSupervisors(Group $group): bool;
  function canViewAdmin(Group $group): bool;
  function canSetAdmin(Group $group): bool;
  function canAddStudent(Group $group, User $student): bool;
  function canRemoveStudent(Group $group, User $student): bool;
  function canAddSupervisor(Group $group, User $supervisor): bool;
  function canRemoveSupervisor(Group $group, User $supervisor): bool;
  function canViewStats(Group $group): bool;
  function canViewStudentStats(Group $group, User $student): bool;
  function canAddSubgroup(Group $group): bool;
  function canUpdate(Group $group): bool;
  function canRemove(Group $group): bool;
  function canViewExercises(Group $group): bool;
  function canAssignExercise(Group $group, Exercise $exercise): bool;
  function canCreateExercise(Group $group): bool;
  function canViewPublicDetail(Group $group): bool;
}
