<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SubmissionFailedException;
use App\Helpers\ExerciseConfig\ExerciseConfigChecker;
use App\Helpers\ExercisesConfig;
use App\Helpers\FileStorageManager;
use App\Model\Entity\Assignment;
use App\Model\Entity\ExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\AttachmentFile;
use App\Model\Repository\Assignments;
use App\Model\Repository\AttachmentFiles;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Model\Repository\ExerciseFiles;
use App\Model\Repository\UploadedFiles;
use App\Security\ACL\IExercisePermissions;
use Exception;

/**
 * Endpoints for exercise files manipulation
 * @LoggedIn
 */
class ExerciseFilesPresenter extends BasePresenter
{
    /**
     * @var Exercises
     * @inject
     */
    public $exercises;

    /**
     * @var Assignments
     * @inject
     */
    public $assignments;

    /**
     * @var UploadedFiles
     * @inject
     */
    public $uploadedFiles;

    /**
     * @var ExerciseFiles
     * @inject
     */
    public $exerciseFiles;

    /**
     * @var AttachmentFiles
     * @inject
     */
    public $attachmentFiles;

    /**
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;

    /**
     * @var IExercisePermissions
     * @inject
     */
    public $exerciseAcl;

    /**
     * @var ExercisesConfig
     * @inject
     */
    public $restrictionsConfig;

    /**
     * @var ExerciseConfigChecker
     * @inject
     */
    public $configChecker;

    public function checkUploadExerciseFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot update this exercise.");
        }
    }

    /**
     * Associate exercise files with an exercise and upload them to remote file server
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws SubmissionFailedException
     */
    #[Post("files", new VMixed(), "Identifiers of exercise files", nullable: true)]
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionUploadExerciseFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
        $currentFiles = [];
        $totalFileSize = 0;

        /** @var ExerciseFile $file */
        foreach ($exercise->getExerciseFiles() as $file) {
            $currentFiles[$file->getName()] = $file;
            $totalFileSize += $file->getFileSize();
        }

        $totalFileCount = count($exercise->getExerciseFiles());

        /** @var UploadedFile $file */
        foreach ($files as $file) {
            if (get_class($file) !== UploadedFile::class) {
                throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
            }

            if (array_key_exists($file->getName(), $currentFiles)) {
                /** @var ExerciseFile $currentFile */
                $currentFile = $currentFiles[$file->getName()];
                $exercise->getExerciseFiles()->removeElement($currentFile);
                $totalFileSize -= $currentFile->getFileSize();
            } else {
                $totalFileCount += 1;
            }

            $totalFileSize += $file->getFileSize();
        }

        $fileCountLimit = $this->restrictionsConfig->getExerciseFileCountLimit();
        if ($totalFileCount > $fileCountLimit) {
            throw new InvalidApiArgumentException(
                'files',
                "The number of files would exceed the configured limit ($fileCountLimit)"
            );
        }

        $sizeLimit = $this->restrictionsConfig->getExerciseFileSizeLimit();
        if ($totalFileSize > $sizeLimit) {
            throw new InvalidApiArgumentException(
                'files',
                "The total size of files would exceed the configured limit ($sizeLimit)"
            );
        }

        /** @var UploadedFile $file */
        foreach ($files as $file) {
            $hash = $this->fileStorage->storeUploadedExerciseFile($file);
            $exerciseFile = ExerciseFile::fromUploadedFileAndExercise($file, $exercise, $hash);
            $this->uploadedFiles->persist($exerciseFile, false);
            $this->uploadedFiles->remove($file, false);
        }

        $exercise->updatedNow();
        $this->exercises->flush();
        $this->uploadedFiles->flush();

        $this->configChecker->check($exercise);
        $this->exercises->flush();

        $this->sendSuccessResponse($exercise->getExerciseFiles()->getValues());
    }

    public function checkGetExerciseFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException("You cannot view exercise files for this exercise.");
        }
    }

    /**
     * Get list of all exercise files for an exercise
     * @GET
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionGetExerciseFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $this->sendSuccessResponse($exercise->getExerciseFiles()->getValues());
    }

    public function checkDeleteExerciseFile(string $id, string $fileId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot delete exercise files for this exercise.");
        }
    }

    /**
     * Delete exercise file with given id
     * @DELETE
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    #[Path("fileId", new VString(), "identification of file", required: true)]
    public function actionDeleteExerciseFile(string $id, string $fileId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $file = $this->exerciseFiles->findOrThrow($fileId);

        $exercise->updatedNow();
        $exercise->removeExerciseFile($file);
        $this->exercises->flush();

        $this->configChecker->check($exercise);
        $this->exercises->flush();

        $this->sendSuccessResponse("OK");
    }

    public function checkDownloadExerciseFilesArchive(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException("You cannot access archive of exercise files");
        }
    }

    /**
     * Download archive containing all files for exercise.
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    #[Path("id", new VUuid(), "of exercise", required: true)]
    public function actionDownloadExerciseFilesArchive(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        $files = [];
        foreach ($exercise->getExerciseFiles() as $file) {
            /** @var ExerciseFile $file */
            $files[$file->getName()] = $file->getFile($this->fileStorage);
        }

        $this->sendZipFilesResponse($files, "exercise-files-{$id}.zip", true);
    }

    public function checkUploadAttachmentFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot upload files for this exercise.");
        }
    }

    /**
     * Associate attachment exercise files with an exercise
     * @POST
     * @DEPRECATED attachment files were unified with exercise files
     * @throws ForbiddenRequestException
     */
    #[Post("files", new VMixed(), "Identifiers of attachment files", nullable: true)]
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionUploadAttachmentFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
        $currentAttachmentFiles = [];

        /** @var AttachmentFile $file */
        foreach ($exercise->getAttachmentFiles() as $file) {
            $currentAttachmentFiles[$file->getName()] = $file;
        }

        /** @var UploadedFile $file */
        foreach ($files as $file) {
            if (!($file instanceof UploadedFile)) {
                throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
            }

            if (array_key_exists($file->getName(), $currentAttachmentFiles)) {
                $currentFile = $currentAttachmentFiles[$file->getName()];
                $exercise->getAttachmentFiles()->removeElement($currentFile);
            }

            $attachmentFile = AttachmentFile::fromUploadedFile($file, $exercise);
            $this->uploadedFiles->persist($attachmentFile);
            try {
                $this->fileStorage->storeUploadedAttachmentFile($file, $attachmentFile);
            } catch (Exception $e) {
                $this->uploadedFiles->remove($attachmentFile);
                throw $e;
            }
            $this->uploadedFiles->remove($file, false);
        }

        $exercise->updatedNow();
        $this->exercises->flush();
        $this->uploadedFiles->flush();
        $this->sendSuccessResponse($exercise->getAttachmentFiles()->getValues());
    }

    public function checkGetAttachmentFiles(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot view attachment files for this exercise.");
        }
    }

    /**
     * Get a list of all attachment files for an exercise
     * @GET
     * @DEPRECATED attachment files were unified with exercise files
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionGetAttachmentFiles(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        $this->sendSuccessResponse($exercise->getAttachmentFiles()->getValues());
    }

    public function checkDeleteAttachmentFile(string $id, string $fileId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $file = $this->attachmentFiles->findOrThrow($fileId);
        if (!$file->getExercises()->contains($exercise)) {
            throw new BadRequestException("Selected file is not an attachment file for given exercise.");
        }
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot delete attachment files for this exercise.");
        }
    }

    /**
     * Delete attachment exercise file with given id
     * @DELETE
     * @DEPRECATED attachment files were unified with exercise files
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    #[Path("fileId", new VString(), "identification of file", required: true)]
    public function actionDeleteAttachmentFile(string $id, string $fileId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $file = $this->attachmentFiles->findOrThrow($fileId);

        $exercise->updatedNow();
        $exercise->removeAttachmentFile($file);
        $this->exercises->flush();

        $this->attachmentFiles->refresh($file);
        if ($file->getExercises()->isEmpty()) {
            // file has no attachments to exercises, let's check the assignments
            $isUsed = false;
            foreach ($file->getAssignments() as $assignment) {
                /** @var Assignment $assignment */
                $group = $assignment->getGroup();
                if ($group && !$group->isArchived()) {
                    $isUsed = true;  // only non-archived assignments are considered relevant
                    break;
                }
            }

            if (!$isUsed) {
                $this->fileStorage->deleteAttachmentFile($file);

                if ($file->getAssignments()->isEmpty()) {
                    // only if no attachments exists (except for deleted ones)
                    // remove all links to deleted entities and remove the file record
                    foreach ($file->getExercisesAndIReallyMeanAllOkay() as $exercise) {
                        /** @var Exercise $exercise */
                        $exercise->removeAttachmentFile($file);
                        $this->exercises->persist($exercise, false);
                    }
                    foreach ($file->getAssignmentsAndIReallyMeanAllOkay() as $assignment) {
                        /** @var Assignment $assignment */
                        $assignment->removeAttachmentFile($file);
                        $this->assignments->persist($assignment, false);
                    }

                    $this->attachmentFiles->remove($file);
                }
            }
        }

        $this->sendSuccessResponse("OK");
    }

    public function checkDownloadAttachmentFilesArchive(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException("You cannot access archive of exercise attachment files");
        }
    }

    /**
     * Download archive containing all attachment files for exercise.
     * @GET
     * @DEPRECATED attachment files were unified with exercise files
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    #[Path("id", new VUuid(), "of exercise", required: true)]
    public function actionDownloadAttachmentFilesArchive(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        $files = [];
        foreach ($exercise->getAttachmentFiles() as $file) {
            /** @var AttachmentFile $file */
            $files[$file->getName()] = $file->getFile($this->fileStorage);
        }
        $this->sendZipFilesResponse($files, "exercise-attachment-{$id}.zip");
    }
}
