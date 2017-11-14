<?php
namespace App\Security\ACL;


use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;

interface IAssignmentSolutionPermissions {
  function canViewAll(): bool;
  function canViewDetail(AssignmentSolution $assignmentSolution): bool;
  function canSetBonusPoints(AssignmentSolution $assignmentSolution): bool;
  function canSetAccepted(AssignmentSolution $assignmentSolution): bool;
  function canViewResubmissions(AssignmentSolution $assignmentSolution): bool;

  function canViewEvaluation(AssignmentSolution $assignmentSolution, ?AssignmentSolutionSubmission $submission): bool;
  function canViewEvaluationDetails(AssignmentSolution $assignmentSolution, ?AssignmentSolutionSubmission $submission): bool;
  function canViewEvaluationValues(AssignmentSolution $assignmentSolution, ?AssignmentSolutionSubmission $submission): bool;
  function canDownloadResultArchive(AssignmentSolution $assignmentSolution, ?AssignmentSolutionSubmission $submission): bool;
}
