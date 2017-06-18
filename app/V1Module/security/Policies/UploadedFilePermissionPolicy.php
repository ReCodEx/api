<?php
namespace App\Security\Policies;

use App\Model\Entity\AdditionalExerciseFile;
use App\Model\Entity\Group;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Assignments;
use App\Model\Repository\UploadedFiles;
use App\Security\Identity;

class UploadedFilePermissionPolicy implements IPermissionPolicy {
  /** @var Assignments */
  private $assignments;

  /** @var UploadedFiles */
  private $files;

  public function __construct(Assignments $assignments, UploadedFiles $files) {
    $this->assignments = $assignments;
    $this->files = $files;
  }

  public function getAssociatedClass() {
    return UploadedFile::class;
  }

  public function isAccessible(Identity $identity, UploadedFile $file) {
    if ($file->isPublic()) {
      return TRUE;
    }

    $user = $identity->getUserData();
    if ($user === NULL) {
      return FALSE;
    }

    $isUserSupervisor = FALSE;
    $isFileRelatedToUsersAssignment = FALSE;

    if ($file instanceof AdditionalExerciseFile) {
      foreach ($file->getExercises() as $exercise) {
        if ($this->assignments->isAssignedToUser($exercise, $user)) {
          $isFileRelatedToUsersAssignment = TRUE;
          break;
        }
      }
    }

    /** @var Group $group */
    $group = $this->files->findGroupForFile($file);
    if ($group && ($group->isSupervisorOf($user) || $group->isAdminOf($user))) {
      $isUserSupervisor = TRUE;
    }

    $isUserOwner = $file->getUser()->getId() === $user->getId();

    return $isUserOwner || $isUserSupervisor || $isFileRelatedToUsersAssignment;
  }
}