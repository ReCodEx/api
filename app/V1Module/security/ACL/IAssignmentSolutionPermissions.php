<?php

namespace App\Security\ACL;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ReviewComment;

interface IAssignmentSolutionPermissions
{
    public function canViewAll(): bool;

    public function canViewDetail(AssignmentSolution $assignmentSolution): bool;

    public function canUpdate(AssignmentSolution $assignmentSolution): bool;

    public function canDelete(AssignmentSolution $assignmentSolution): bool;

    public function canSetBonusPoints(AssignmentSolution $assignmentSolution): bool;

    public function canSetFlag(AssignmentSolution $assignmentSolution): bool;

    public function canSetFlagAsStudent(AssignmentSolution $assignmentSolution): bool;

    public function canViewResubmissions(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluation(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluationDetails(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluationValues(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluationJudgeStdout(AssignmentSolution $assignmentSolution): bool;

    public function canViewEvaluationJudgeStderr(AssignmentSolution $assignmentSolution): bool;

    public function canDeleteEvaluation(AssignmentSolution $assignmentSolution): bool;

    public function canDownloadResultArchive(AssignmentSolution $assignmentSolution): bool;

    public function canViewReview(AssignmentSolution $assignmentSolution): bool;

    public function canReview(AssignmentSolution $assignmentSolution): bool;

    public function canDeleteReview(AssignmentSolution $assignmentSolution): bool;

    public function canAddReviewComment(AssignmentSolution $assignmentSolution): bool;

    public function canEditReviewComment(AssignmentSolution $assignmentSolution, ReviewComment $reviewComment): bool;

    public function canDeleteReviewComment(AssignmentSolution $assignmentSolution, ReviewComment $reviewComment): bool;

    public function canViewDetectedPlagiarisms(AssignmentSolution $assignmentSolution): bool;
}
