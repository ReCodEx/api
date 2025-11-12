<?php

namespace App\Security\Policies;

use App\Model\Entity\Assignment;
use App\Model\Entity\AttachmentFile;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Assignments;
use App\Model\Repository\UploadedFiles;
use App\Security\Identity;

class UploadedFilePermissionPolicy implements IPermissionPolicy
{
    /** @var Assignments */
    private $assignments;

    /** @var UploadedFiles */
    private $files;

    public function __construct(Assignments $assignments, UploadedFiles $files)
    {
        $this->assignments = $assignments;
        $this->files = $files;
    }

    public function getAssociatedClass()
    {
        return UploadedFile::class;
    }

    public function isPublic(Identity $identity, UploadedFile $file)
    {
        return $file->isPublic();
    }

    public function isAttachmentFile(Identity $identity, UploadedFile $file)
    {
        return $file instanceof AttachmentFile;
    }

    public function isExercisePublic(Identity $identity, UploadedFile $file)
    {
        return $file instanceof AttachmentFile && $file->getExercises()->exists(
            function ($i, Exercise $exercise) {
                return $exercise->isPublic();
            }
        );
    }

    public function isAuthorOfFileExercises(Identity $identity, UploadedFile $file)
    {
        if (!($file instanceof ExerciseFile)) {
            return false;
        }

        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        foreach ($file->getExercises() as $exercise) {
            if ($exercise->isAuthor($user)) {
                return true;
            }
        }

        return false;
    }


    public function isExerciseFileInGroupUserSupervises(Identity $identity, UploadedFile $file)
    {
        if (!($file instanceof ExerciseFile)) {
            return false;
        }

        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        foreach ($file->getExercises() as $exercise) {
            foreach ($exercise->getGroups() as $group) {
                if ($group->isAdminOrSupervisorOfSubgroup($user)) {
                    return true;  // The user can assign one of the corresponding exercises in hir group.
                }
            }
        }

        return false;
    }


    public function isOwner(Identity $identity, UploadedFile $file)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        return $file->getUser() && $file->getUser()->getId() === $user->getId();
    }

    public function isReferenceSolutionInSupervisedOrObserverdSubGroup(Identity $identity, UploadedFile $file)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        $groups = $this->files->findGroupsForReferenceSolutionFile($file);
        foreach ($groups as $group) {
            if ($group->isNonStudentMemberOfSubgroup($user)) {
                return true;
            }
        }

        return false;
    }

    public function isSolutionInSupervisedOrObservedGroup(Identity $identity, UploadedFile $file)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        $group = $this->files->findGroupForSolutionFile($file);
        return $group && ($group->isObserverOf($user) || $group->isSupervisorOf($user) || $group->isAdminOf($user));
    }

    public function isRelatedToAssignment(Identity $identity, UploadedFile $file)
    {
        $user = $identity->getUserData();
        if ($user === null) {
            return false;
        }

        if ($file instanceof AttachmentFile) {
            foreach ($file->getExercises() as $exercise) {
                foreach ($user->getGroups() as $group) {
                    if ($this->assignments->isAssignedToGroup($exercise, $group)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
