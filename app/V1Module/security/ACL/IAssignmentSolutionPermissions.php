<?php

namespace App\Security\ACL;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;

interface IAssignmentSolutionPermissions
{
    function canViewAll(): bool;

    function canViewDetail(AssignmentSolution $assignmentSolution): bool;

    function canUpdate(AssignmentSolution $assignmentSolution): bool;

    function canDelete(AssignmentSolution $assignmentSolution): bool;

    function canSetBonusPoints(AssignmentSolution $assignmentSolution): bool;

    function canSetAccepted(AssignmentSolution $assignmentSolution): bool;

    function canSetFlag(AssignmentSolution $assignmentSolution): bool;

    function canViewResubmissions(AssignmentSolution $assignmentSolution): bool;

    function canViewEvaluation(AssignmentSolution $assignmentSolution): bool;

    function canViewEvaluationDetails(AssignmentSolution $assignmentSolution): bool;

    function canViewEvaluationValues(AssignmentSolution $assignmentSolution): bool;

    function canViewEvaluationJudgeStdout(AssignmentSolution $assignmentSolution): bool;

    function canViewEvaluationJudgeStderr(AssignmentSolution $assignmentSolution): bool;

    function canDeleteEvaluation(AssignmentSolution $assignmentSolution): bool;

    function canDownloadResultArchive(AssignmentSolution $assignmentSolution): bool;
}
