<?php
namespace App\Security\ACL;


use App\Model\Entity\AssignmentSolution;

interface ISubmissionPermissions {
  function canViewAll(): bool;
  function canViewDetail(AssignmentSolution $submission): bool;
  function canViewEvaluation(AssignmentSolution $submission): bool;
  function canViewEvaluationDetails(AssignmentSolution $submission): bool;
  function canViewEvaluationValues(AssignmentSolution $submission): bool;
  function canSetBonusPoints($submission): bool;
  function canSetAccepted($submission): bool;
  function canDownloadResultArchive($submission): bool;
}
