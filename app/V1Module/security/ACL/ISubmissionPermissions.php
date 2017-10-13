<?php
namespace App\Security\ACL;


use App\Model\Entity\Submission;

interface ISubmissionPermissions {
  function canViewAll(): bool;
  function canViewDetail(Submission $submission): bool;
  function canViewEvaluation(Submission $submission): bool;
  function canViewEvaluationDetails(Submission $submission): bool;
  function canViewEvaluationValues(Submission $submission): bool;
  function canSetBonusPoints($submission): bool;
  function canSetAccepted($submission): bool;
  function canDownloadResultArchive($submission): bool;
}
