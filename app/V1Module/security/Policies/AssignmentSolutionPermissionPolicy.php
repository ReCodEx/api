<?php

namespace App\Security\Policies;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Security\Identity;

class AssignmentSolutionPermissionPolicy implements IPermissionPolicy
{

    public function getAssociatedClass()
    {
        return AssignmentSolution::class;
    }

    public function isSupervisorOrAdmin(Identity $identity, AssignmentSolution $solution)
    {
        $assignment = $solution->getAssignment();
        if ($assignment === null) {
            return false;
        }

        $group = $assignment->getGroup();
        $user = $identity->getUserData();

        if ($user === null) {
            return false;
        }

        return $group && ($group->isSupervisorOf($user) || $group->isAdminOf($user));
    }

    public function isObserverOrBetter(Identity $identity, AssignmentSolution $solution)
    {
        $assignment = $solution->getAssignment();
        if ($assignment === null) {
            return false;
        }

        $group = $assignment->getGroup();
        $user = $identity->getUserData();

        if ($user === null) {
            return false;
        }

        return $group && ($group->isObserverOf($user) || $group->isSupervisorOf($user) || $group->isAdminOf($user));
    }

    public function isAuthor(Identity $identity, AssignmentSolution $solution)
    {
        $user = $identity->getUserData();
        return $user !== null && $user === $solution->getSolution()->getAuthor();
    }

    public function areEvaluationDetailsPublic(Identity $identity, AssignmentSolution $solution)
    {
        return $solution->getAssignment() && $solution->getAssignment()->getCanViewLimitRatios();
    }

    public function areJudgeStdoutsPublic(Identity $identity, AssignmentSolution $solution)
    {
        return $solution->getAssignment() && $solution->getAssignment()->getCanViewJudgeStdout();
    }

    public function areJudgeStderrsPublic(Identity $identity, AssignmentSolution $solution)
    {
        return $solution->getAssignment() && $solution->getAssignment()->getCanViewJudgeStderr();
    }

    public function isInActiveGroup(Identity $identity, AssignmentSolution $solution)
    {
        $assignment = $solution->getAssignment();
        if (!$assignment) {
            return false;
        }

        $group = $assignment->getGroup();
        return $group && !$group->isArchived(); // active = not deleted and not archived
    }
}
