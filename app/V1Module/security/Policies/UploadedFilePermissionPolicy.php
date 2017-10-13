<?php
namespace App\Security\Policies;

use App\Model\Entity\AdditionalExerciseFile;
use App\Model\Entity\Exercise;
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

  public function isPublic(Identity $identity, UploadedFile $file) {
    return $file->isPublic();
  }

  public function isAdditionalExerciseFile(Identity $identity, UploadedFile $file) {
    return $file instanceof AdditionalExerciseFile;
  }

  public function isExercisePublic(Identity $identity, UploadedFile $file) {
    return $file instanceof AdditionalExerciseFile && $file->getExercises()->exists(function ($i, Exercise $exercise) {
      return $exercise->isPublic();
    });
  }

  public function isOwner(Identity $identity, UploadedFile $file) {
    $user = $identity->getUserData();
    if ($user === NULL) {
      return FALSE;
    }

    return $file->getUser()->getId() === $user->getId();
  }

  public function isReferenceSolutionInSupervisedSubGroup(Identity $identity, UploadedFile $file) {
    $user = $identity->getUserData();
    if ($user === NULL) {
      return FALSE;
    }

    $groups = $this->files->findGroupsForReferenceSolutionFile($file);
    foreach ($groups as $group) {
      if ($group->isAdminOrSupervisorOfSubgroup($user)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  public function isSolutionInSupervisedGroup(Identity $identity, UploadedFile $file) {
    $user = $identity->getUserData();
    if ($user === NULL) {
      return FALSE;
    }

    $group = $this->files->findGroupForSolutionFile($file);
    return $group && ($group->isSupervisorOf($user) || $group->isAdminOf($user));
  }

  public function isRelatedToAssignment(Identity $identity, UploadedFile $file) {
    $user = $identity->getUserData();
    if ($user === NULL) {
      return FALSE;
    }

    if ($file instanceof AdditionalExerciseFile) {
      foreach ($file->getExercises() as $exercise) {
        foreach ($user->getGroups() as $group) {
          if ($this->assignments->isAssignedToGroup($exercise, $group)) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }
}
