<?php

namespace App\Security\ACL;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;

interface IAssignmentSolutionPermissions
{
    public function canViewAll(): bool;

    public function canViewDetail(AssignmentSolution $assignmentSolution): bool;

    public function canUpdate(AssignmentSolution $assignmentSolution): bool;

    public function canDelete(AssignmentSolution $assignmentSolution): bool;

    public function canSetBonusPoints(AssignmentSolution $assignmentSolution): bool;

    public function canSetAccepted(AssignmentSolution $assignmentSolution): bool;

    public function canSetFlag(AssignmentSolution $assignmentSolution): bool;

    public function canViewResubmissions(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluation(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluationDetails(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluationValues(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluationJudgeStdout(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluationJudgeStderr(AssignmentSolution $assignmentSolution): bool;

    public function canDeleteEvaluation(AssignmentSolution $assignmentSolution): bool;

    public function canDownloadResultArchive(AssignmentSolution $assignmentSolution): bool;
}
