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

    public function isSupervisor(Identity $identity, AssignmentSolution $solution)
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

    public function isAuthor(Identity $identity, AssignmentSolution $solution)
    {
        $user = $identity->getUserData();
        return $user !== null && $user === $solution->getSolution()->getAuthor();
    }

    public function areEvaluationDetailsPublic(Identity $identity, AssignmentSolution $solution)
    {
        return $solution->getAssignment() && $solution->getAssignment()->getCanViewLimitRatios();
    }

    public function areJudgeOutputsPublic(Identity $identity, AssignmentSolution $solution)
    {
        return $solution->getAssignment() && $solution->getAssignment()->getCanViewJudgeOutputs();
    }
}
