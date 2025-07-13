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
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\AttachmentFile;
use App\Model\Repository\Assignments;
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


    /**
     * Associate supplementary files with an exercise and upload them to remote file server
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws SubmissionFailedException
     */
    #[Post("files", new VMixed(), "Identifiers of supplementary files", nullable: true)]
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionUploadSupplementaryFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Get list of all supplementary files for an exercise
     * @GET
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionGetSupplementaryFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Delete supplementary exercise file with given id
     * @DELETE
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    #[Path("fileId", new VString(), "identification of file", required: true)]
    public function actionDeleteSupplementaryFile(string $id, string $fileId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Download archive containing all supplementary files for exercise.
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    #[Path("id", new VUuid(), "of exercise", required: true)]
    public function actionDownloadSupplementaryFilesArchive(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Associate attachment exercise files with an exercise
     * @POST
     * @throws ForbiddenRequestException
     */
    #[Post("files", new VMixed(), "Identifiers of attachment files", nullable: true)]
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionUploadAttachmentFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Get a list of all attachment files for an exercise
     * @GET
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionGetAttachmentFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Delete attachment exercise file with given id
     * @DELETE
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    #[Path("fileId", new VString(), "identification of file", required: true)]
    public function actionDeleteAttachmentFile(string $id, string $fileId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Download archive containing all attachment files for exercise.
     * @GET
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    #[Path("id", new VUuid(), "of exercise", required: true)]
    public function actionDownloadAttachmentFilesArchive(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
