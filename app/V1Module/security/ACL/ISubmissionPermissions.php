<?php
namespace App\Security\ACL;


use App\Model\Entity\Submission;

interface ISubmissionPermissions {
  function canViewDetail(Submission $submission);
}