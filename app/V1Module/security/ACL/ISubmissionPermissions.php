<?php
namespace App\Security\ACL;


use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;

interface ISubmissionPermissions {
  function canViewAll(): bool;
  function canViewDetail(AssignmentSolution $submission): bool;
  function canSetBonusPoints(AssignmentSolution $submission): bool;
  function canSetAccepted(AssignmentSolution $submission): bool;

  function canViewEvaluation(AssignmentSolution $submission, AssignmentSolutionSubmission $solutionSubmission): bool;
  function canViewEvaluationDetails(AssignmentSolution $submission, AssignmentSolutionSubmission $solutionSubmission): bool;
  function canViewEvaluationValues(AssignmentSolution $submission, AssignmentSolutionSubmission $solutionSubmission): bool;
  function canDownloadResultArchive(AssignmentSolution $submission, AssignmentSolutionSubmission $solutionSubmission): bool;
}
