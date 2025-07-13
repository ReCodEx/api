<?php

namespace App\V1Module\Presenters;

use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\FileStorage\FileStorageException;
use App\Helpers\FileStorageManager;
use App\Helpers\UploadsConfig;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\SupplementaryExerciseFiles;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\UploadedPartialFiles;
use App\Model\Repository\PlagiarismDetectedSimilarFiles;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\UploadedPartialFile;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\SolutionZipFile;
use App\Security\ACL\IUploadedFilePermissions;
use App\Security\ACL\IUploadedPartialFilePermissions;
use App\Security\ACL\IAssignmentSolutionPermissions;
use Nette\Utils\Strings;
use Nette\Http\IResponse;
use Tracy\ILogger;
use DateTime;
use Exception;

/**
 * Endpoints for management of uploaded files
 */
class UploadedFilesPresenter extends BasePresenter
{
    public const FILENAME_PATTERN = '#^[a-z0-9\- _\.()\[\]!]+$#i';

    /**
     * @var UploadedFiles
     * @inject
     */
    public $uploadedFiles;

    /**
     * @var UploadedPartialFiles
     * @inject
     */
    public $uploadedPartialFiles;

    /**
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;

    /**
     * @var Assignments
     * @inject
     */
    public $assignments;

    /**
     * @var AssignmentSolutions
     * @inject
     */
    public $assignmentSolutions;

    /**
     * @var IUploadedFilePermissions
     * @inject
     */
    public $uploadedFileAcl;

    /**
     * @var IUploadedPartialFilePermissions
     * @inject
     */
    public $uploadedPartialFileAcl;

    /**
     * @var IAssignmentSolutionPermissions
     * @inject
     */
    public $assignmentSolutionAcl;

    /**
     * @var SupplementaryExerciseFiles
     * @inject
     */
    public $supplementaryFiles;

    /**
     * @var PlagiarismDetectedSimilarFiles
     * @inject
     */
    public $detectedSimilarFiles;

    /**
     * @var UploadsConfig
     * @inject
     */
    public $uploadsConfig;

    /**
     * @var ILogger
     * @inject
     */
    public $logger;

    public function noncheckDetail(string $id)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        if (!$this->uploadedFileAcl->canViewDetail($file)) {
            throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
        }
    }

    /**
     * Get details of a file
     * @GET
     * @LoggedIn
     * @param string $id Identifier of the uploaded file
     */
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDownload(string $id, ?string $entry = null, ?string $similarSolutionId = null)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        if ($this->uploadedFileAcl->canDownload($file)) {
            return;  // this is sufficient noncheck for most downloads
        }

        if ($file instanceof SolutionFile && $similarSolutionId) {
            // special noncheck using similar solution hint
            // similar solution refers to anoter solution which has detected similarities in this file
            // (so whoever can see plagiarisms of the original solution may see this file)
            $similarSolution = $this->assignmentSolutions->findOrThrow($similarSolutionId);
            $fileSolution = $this->assignmentSolutions->findOneBy([ 'solution' => $file->getSolution() ]);

            if (
                $fileSolution &&
                $this->assignmentSolutionAcl->canViewDetectedPlagiarisms($similarSolution) &&
                $this->detectedSimilarFiles->findByTestedAndSimilarSolution(
                    $similarSolution,
                    $fileSolution,
                    $file,
                    $entry
                )
            ) {
                return;  // the user can see plagiarisms of given solution and the file is detected as similar
            }
        }

        throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }

    /**
     * Download a file
     * @GET
     * @param string $id Identifier of the file
     * @Param(type="query", name="entry", required=false, validation="string:1..",
     *        description="Name of the entry in the ZIP archive (if the target file is ZIP)")
     * @Param(type="query", name="similarSolutionId", required=false, validation="string:36",
     *        description="Id of an assignment solution which has detected possible plagiarism in this file.
     *                     This is basically a shortcut (hint) for ACLs.")
     * @throws \Nette\Application\AbortException
     * @throws \Nette\Application\BadRequestException
     */
    public function actionDownload(string $id, ?string $entry = null)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckContent(string $id, ?string $entry = null, ?string $similarSolutionId = null)
    {
        $this->noncheckDownload($id, $entry, $similarSolutionId);
    }

    /**
     * Get the contents of a file
     * @GET
     * @param string $id Identifier of the file
     * @Param(type="query", name="entry", required=false, validation="string:1..",
     *        description="Name of the entry in the ZIP archive (if the target file is ZIP)")
     * @Param(type="query", name="similarSolutionId", required=false, validation="string:36",
     *        description="Id of an assignment solution which has detected possible plagiarism in this file.
     *                     This is basically a shortcut (hint) for ACLs.")
     */
    public function actionContent(string $id, ?string $entry = null)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDigest(string $id)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        if (!$this->uploadedFileAcl->canDownload($file)) {
            throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
        }
    }

    /**
     * Compute a digest using a hashing algorithm. This feature is intended for upload nonchecksums only.
     * In the future, we might want to add algorithm selection via query parameter (default is SHA1).
     * @GET
     * @param string $id Identifier of the file
     */
    public function actionDigest(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpload()
    {
        if (!$this->uploadedFileAcl->canUpload()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Upload a file
     * @POST
     * @throws InvalidArgumentException for files with invalid names
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws CannotReceiveUploadedFileException
     * @throws InternalServerException
     */
    public function actionUpload()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckStartPartial()
    {
        // actually, this is same as upload
        if (!$this->uploadedFileAcl->canUpload()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Start new upload per-partes. This process expects the file is uploaded as a sequence of PUT requests,
     * each one carrying a chunk of data. Once all the chunks are in place, the complete request assembles
     * them together in one file and transforms UploadPartialFile into UploadFile entity.
     * @POST
     * @Param(type="post", name="name", required=true, validation="string:1..255",
     *        description="Name of the uploaded file.")
     * @Param(type="post", name="size", required=true, validation="numericint", description="Total size in bytes.")
     */
    public function actionStartPartial()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAppendPartial(string $id)
    {
        $file = $this->uploadedPartialFiles->findOrThrow($id);
        if (!$this->uploadedPartialFileAcl->canAppendPartial($file)) {
            throw new ForbiddenRequestException("You cannot add chunks to a per-partes upload started by another user");
        }
    }

    /**
     * Add another chunk to partial upload.
     * @PUT
     * @param string $id Identifier of the file
     * @Param(type="query", name="offset", required="true", validation="numericint",
     *        description="Offset of the chunk for verification")
     * @throws InvalidArgumentException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws CannotReceiveUploadedFileException
     * @throws InternalServerException
     */
    public function actionAppendPartial(string $id, int $offset)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCancelPartial(string $id)
    {
        $file = $this->uploadedPartialFiles->findOrThrow($id);
        if (!$this->uploadedPartialFileAcl->canCancelPartial($file)) {
            throw new ForbiddenRequestException("You cannot cancel a per-partes upload started by another user");
        }
    }

    /**
     * Cancel partial upload and remove all uploaded chunks.
     * @DELETE
     */
    public function actionCancelPartial(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCompletePartial(string $id)
    {
        $file = $this->uploadedPartialFiles->findOrThrow($id);
        if (!$this->uploadedPartialFileAcl->canCompletePartial($file)) {
            throw new ForbiddenRequestException("You cannot complete a per-partes upload started by another user");
        }
    }

    /**
     * Finalize partial upload and convert the partial file into UploadFile.
     * All data chunks are extracted from the store, assembled into one file, and is moved back into the store.
     * @POST
     */
    public function actionCompletePartial(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDownloadSupplementaryFile(string $id)
    {
        $file = $this->supplementaryFiles->findOrThrow($id);
        if (!$this->uploadedFileAcl->canDownloadSupplementaryFile($file)) {
            throw new ForbiddenRequestException("You are not allowed to download file '{$file->getId()}");
        }
    }

    /**
     * Download supplementary file
     * @GET
     * @param string $id Identifier of the file
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws \Nette\Application\AbortException
     */
    public function actionDownloadSupplementaryFile(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
