<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SubmissionFailedException;
use App\Helpers\ExerciseConfig\ExerciseConfigChecker;
use App\Helpers\ExercisesConfig;
use App\Helpers\FileStorageManager;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\AttachmentFile;
use App\Model\Repository\AttachmentFiles;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Model\Repository\SupplementaryExerciseFiles;
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
     * @var UploadedFiles
     * @inject
     */
    public $uploadedFiles;

    /**
     * @var SupplementaryExerciseFiles
     * @inject
     */
    public $supplementaryFiles;

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

    public function noncheckUploadSupplementaryFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot update this exercise.");
        }
    }

    /**
     * Associate supplementary files with an exercise and upload them to remote file server
     * @POST
     * @Param(type="post", name="files", description="Identifiers of supplementary files")
     * @param string $id identification of exercise
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws SubmissionFailedException
     */
    public function actionUploadSupplementaryFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetSupplementaryFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException("You cannot view supplementary files for this exercise.");
        }
    }

    /**
     * Get list of all supplementary files for an exercise
     * @GET
     * @param string $id identification of exercise
     */
    public function actionGetSupplementaryFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteSupplementaryFile(string $id, string $fileId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot delete supplementary files for this exercise.");
        }
    }

    /**
     * Delete supplementary exercise file with given id
     * @DELETE
     * @param string $id identification of exercise
     * @param string $fileId identification of file
     * @throws ForbiddenRequestException
     */
    public function actionDeleteSupplementaryFile(string $id, string $fileId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDownloadSupplementaryFilesArchive(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException("You cannot access archive of exercise supplementary files");
        }
    }

    /**
     * Download archive containing all supplementary files for exercise.
     * @GET
     * @param string $id of exercise
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    public function actionDownloadSupplementaryFilesArchive(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUploadAttachmentFiles(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot upload files for this exercise.");
        }
    }

    /**
     * Associate attachment exercise files with an exercise
     * @POST
     * @Param(type="post", name="files", description="Identifiers of attachment files")
     * @param string $id identification of exercise
     * @throws ForbiddenRequestException
     */
    public function actionUploadAttachmentFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetAttachmentFiles(string $id)
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
     * @param string $id identification of exercise
     * @throws ForbiddenRequestException
     */
    public function actionGetAttachmentFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteAttachmentFile(string $id, string $fileId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot delete attachment files for this exercise.");
        }
    }

    /**
     * Delete attachment exercise file with given id
     * @DELETE
     * @param string $id identification of exercise
     * @param string $fileId identification of file
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionDeleteAttachmentFile(string $id, string $fileId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDownloadAttachmentFilesArchive(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException("You cannot access archive of exercise attachment files");
        }
    }

    /**
     * Download archive containing all attachment files for exercise.
     * @GET
     * @param string $id of exercise
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    public function actionDownloadAttachmentFilesArchive(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
