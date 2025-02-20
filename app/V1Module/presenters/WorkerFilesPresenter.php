<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Helpers\BasicAuthHelper;
use App\Helpers\WorkerFilesConfig;
use App\Helpers\FileStorageManager;
use App\Exceptions\HttpBasicAuthException;
use App\Exceptions\NotFoundException;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\UploadedFileException;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;
use Exception;

/**
 * Endpoints used by workers to exchange files with core.
 * These endpoints take over responsibilities of FileServer component when integrated file-storage is used.
 */
class WorkerFilesPresenter extends BasePresenter
{
    /**
     * @var WorkerFilesConfig
     * @inject
     */
    public $config;

    /**
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;

    /**
     * @var AssignmentSolutionSubmissions
     * @inject
     */
    public $assignmentSubmissions;

    /**
     * @var ReferenceSolutionSubmissions
     * @inject
     */
    public $referenceSubmissions;

    /**
     * Return the right submissions repository based on given type.
     * @return ReferenceSolutionSubmissions|AssignmentSolutionSubmissions
     */
    private function getSubmissionsRepository(string $type)
    {
        if ($type === ReferenceSolutionSubmission::JOB_TYPE) {
            return $this->referenceSubmissions;
        } elseif ($type === AssignmentSolutionSubmission::JOB_TYPE) {
            return $this->assignmentSubmissions;
        } else {
            throw new InvalidArgumentException("Invalid submission type '$type'");
        }
    }

    /**
     * The actions of this presenter have specific
     * @throws WrongCredentialsException
     * @throws HttpBasicAuthException
     * @throws ForbiddenRequestException
     */
    public function startup()
    {
        if ($this->config->isEnabled() === false) {
            throw new ForbiddenRequestException("Worker files interface is disabled in the configuration.");
        }

        $req = $this->getHttpRequest();
        list($username, $password) = BasicAuthHelper::getCredentials($req);

        $isAuthCorrect = $username === $this->config->getAuthUsername()
            && $password === $this->config->getAuthPassword();

        if (!$isAuthCorrect) {
            throw new WrongCredentialsException();
        }

        parent::startup();
    }

    /**
     * Sends over a ZIP file containing submitted files and YAML job config.
     * The ZIP is created if necessary.
     * @GET
     */
    #[Path("type", new VString(), "of the submission job (\"reference\" or \"student\")", required: true)]
    #[Path("id", new VString(), "of the submission whose ZIP archive is to be served", required: true)]
    public function actionDownloadSubmissionArchive(string $type, string $id)
    {
        $file = $this->fileStorage->getWorkerSubmissionArchive($type, $id);
        if (!$file) {
            $submission = $this->getSubmissionsRepository($type)->findOrThrow($id);
            $file = $this->fileStorage->createWorkerSubmissionArchive($submission);
            if (!$file) {
                throw new NotFoundException(
                    "Unable to create worker submission archive (some ingredients may be missing)"
                );
            }
        }
        $this->sendStorageFileResponse($file, "{$type}_{$id}.zip");
    }

    /**
     * Sends over an exercise supplementary file (a data file required by the tests).
     * @GET
     */
    #[Path("hash", new VString(), "identification of the supplementary file", required: true)]
    public function actionDownloadSupplementaryFile(string $hash)
    {
        $file = $this->fileStorage->getSupplementaryFileByHash($hash);
        if (!$file) {
            throw new NotFoundException("Supplementary file not found in the storage");
        }
        $this->sendStorageFileResponse($file, $hash);
    }

    /**
     * Uploads a ZIP archive with results and logs (or everything in case of debug evaluations).
     * @PUT
     * @throws UploadedFileException
     */
    #[Path("type", new VString(), "of the submission job (\"reference\" or \"student\")", required: true)]
    #[Path("id", new VString(), "of the submission whose results archive is being uploaded", required: true)]
    public function actionUploadResultsFile(string $type, string $id)
    {
        try {
            // the save function automatically reads file from php://input
            // this is the only way how to do this efficiently (the uploaded file may be large)
            $this->fileStorage->saveUploadedResultsArchive($type, $id);
        } catch (Exception $e) {
            throw new UploadedFileException($e->getMessage(), $e);
        }
        $this->sendSuccessResponse("OK");
    }
}
