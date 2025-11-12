<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\File;
use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\FileRequestType;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\FileStorage\FileStorageException;
use App\Helpers\FileStorageManager;
use App\Helpers\UploadsConfig;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\ExerciseFiles;
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
     * @var ExerciseFiles
     * @inject
     */
    public $exerciseFiles;

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

    public function checkDetail(string $id)
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
     */
    #[Path("id", new VUuid(), "Identifier of the uploaded file", required: true)]
    public function actionDetail(string $id)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        $this->sendSuccessResponse($file);
    }

    public function checkDownload(string $id, ?string $entry = null, ?string $similarSolutionId = null)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        if ($this->uploadedFileAcl->canDownload($file)) {
            return;  // this is sufficient check for most downloads
        }

        if ($file instanceof SolutionFile && $similarSolutionId) {
            // special check using similar solution hint
            // similar solution refers to another solution which has detected similarities in this file
            // (so whoever can see plagiarisms of the original solution may see this file)
            $similarSolution = $this->assignmentSolutions->findOrThrow($similarSolutionId);
            $fileSolution = $this->assignmentSolutions->findOneBy(['solution' => $file->getSolution()]);

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
     * @throws \Nette\Application\AbortException
     * @throws \Nette\Application\BadRequestException
     */
    #[Query(
        "entry",
        new VString(1),
        "Name of the entry in the ZIP archive (if the target file is ZIP)",
        required: false,
    )]
    #[Query(
        "similarSolutionId",
        new VUuid(),
        "Id of an assignment solution which has detected possible plagiarism in this file. "
            . "This is basically a shortcut (hint) for ACLs.",
        required: false,
    )]
    #[Path("id", new VUuid(), "Identifier of the file", required: true)]
    public function actionDownload(string $id, ?string $entry = null)
    {
        $fileEntity = $this->uploadedFiles->findOrThrow($id);
        if ($entry && $fileEntity instanceof SolutionZipFile) {
            try {
                $file = $fileEntity->getNestedFile($this->fileStorage, $entry);
                $name = basename($entry);
            } catch (FileStorageException $ex) {
                throw new NotFoundException(
                    "File not found in the storage",
                    FrontendErrorMappings::E404_000__NOT_FOUND,
                    ['entry' => $entry],
                    $ex
                );
            }
        } else {
            $file = $fileEntity->getFile($this->fileStorage);
            $name = $fileEntity->getName();
        }
        if (!$file) {
            throw new NotFoundException("File not found in the storage");
        }
        $this->sendStorageFileResponse($file, $name);
    }

    public function checkContent(string $id, ?string $entry = null, ?string $similarSolutionId = null)
    {
        $this->checkDownload($id, $entry, $similarSolutionId);
    }

    /**
     * Get the contents of a file
     * @GET
     */
    #[Query(
        "entry",
        new VString(1),
        "Name of the entry in the ZIP archive (if the target file is ZIP)",
        required: false,
    )]
    #[Query(
        "similarSolutionId",
        new VUuid(),
        "Id of an assignment solution which has detected possible plagiarism in this file. "
            . "This is basically a shortcut (hint) for ACLs.",
        required: false,
    )]
    #[Path("id", new VUuid(), "Identifier of the file", required: true)]
    public function actionContent(string $id, ?string $entry = null)
    {
        $fileEntity = $this->uploadedFiles->findOrThrow($id);
        if ($entry && $fileEntity instanceof SolutionZipFile) {
            try {
                $file = $fileEntity->getNestedFile($this->fileStorage, $entry);
                $size = $file->getSize();
            } catch (FileStorageException $ex) {
                throw new NotFoundException(
                    "File not found in the storage",
                    FrontendErrorMappings::E404_000__NOT_FOUND,
                    ['entry' => $entry],
                    $ex
                );
            }
        } else {
            $file = $fileEntity->getFile($this->fileStorage);
            $size = $fileEntity->getFileSize();
        }
        if (!$file) {
            throw new NotFoundException("File not found in the storage");
        }

        $sizeLimit = $this->uploadsConfig->getMaxPreviewSize();
        $contents = $file->getContents($sizeLimit);

        // Remove UTF BOM prefix...
        $utf8bom = "\xef\xbb\xbf";
        $contents = Strings::replace($contents, "~^$utf8bom~");
        $contents = str_replace("\r\n", "\n", $contents); // normalize line endings

        $fixedContents = @mb_convert_encoding($contents, 'UTF-8', 'UTF-8');

        $this->sendSuccessResponse(
            [
                "content" => $fixedContents,
                "malformedCharacters" => $fixedContents !== $contents,
                "tooLarge" => $size > $sizeLimit,
            ]
        );
    }

    public function checkDigest(string $id)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        if (!$this->uploadedFileAcl->canDownload($file)) {
            throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
        }
    }

    /**
     * Compute a digest using a hashing algorithm. This feature is intended for upload checksums only.
     * In the future, we might want to add algorithm selection via query parameter (default is SHA1).
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the file", required: true)]
    public function actionDigest(string $id)
    {
        $fileEntity = $this->uploadedFiles->findOrThrow($id);
        $file = $fileEntity->getFile($this->fileStorage);
        if (!$file) {
            throw new NotFoundException("File not found in the storage");
        }

        $this->sendSuccessResponse([
            'algorithm' => 'sha1',
            'digest' => $file->getDigest(),
        ]);
    }

    public function checkUpload()
    {
        if (!$this->uploadedFileAcl->canUpload()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Upload a file
     * @POST
     * @throws InvalidApiArgumentException for files with invalid names
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws CannotReceiveUploadedFileException
     * @throws InternalServerException
     */
    #[File(FileRequestType::FormData, "The whole file to be uploaded")]
    public function actionUpload()
    {
        $user = $this->getCurrentUser();
        $files = $this->getRequest()->getFiles();
        if (count($files) === 0) {
            throw new BadRequestException("No file was uploaded");
        } elseif (count($files) > 1) {
            throw new BadRequestException("Too many files were uploaded");
        }

        $file = array_pop($files);
        if (!$file->isOk()) {
            throw new CannotReceiveUploadedFileException(
                sprintf("Cannot receive uploaded file '%s' due to '%d'", $file->getName(), $file->getError()),
                IResponse::S500_InternalServerError,
                FrontendErrorMappings::E500_001__CANNOT_RECEIVE_FILE,
                ["filename" => $file->getName(), "errorCode" => $file->getError()]
            );
        }

        if (!Strings::match($file->getName(), self::FILENAME_PATTERN)) {
            throw new CannotReceiveUploadedFileException(
                sprintf("File name '%s' contains invalid characters", $file->getName()),
                IResponse::S400_BadRequest,
                FrontendErrorMappings::E400_003__UPLOADED_FILE_INVALID_CHARACTERS,
                ["filename" => $file->getName(), "pattern" => self::FILENAME_PATTERN]
            );
        }

        // In theory, this may create race condition (DB record is commited before file is moved).
        // But we need the ID from the database so we can save the file.
        $uploadedFile = new UploadedFile($file->getName(), new DateTime(), $file->getSize(), $user);
        $this->uploadedFiles->persist($uploadedFile);

        try {
            $this->fileStorage->storeUploadedFile($uploadedFile, $file);
        } catch (Exception $e) {
            $this->uploadedFiles->remove($uploadedFile);
            $this->uploadedFiles->flush();
            throw new InternalServerException(
                "Cannot move uploaded file to internal server storage",
                FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
                null,
                $e
            );
        }

        $this->uploadedFiles->flush();
        $this->sendSuccessResponse($uploadedFile);
    }

    public function checkStartPartial()
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
     */
    #[Post("name", new VString(1, 255), "Name of the uploaded file.", required: true)]
    #[Post("size", new VInt(), "Total size in bytes.", required: true)]
    public function actionStartPartial()
    {
        $user = $this->getCurrentUser();
        $name = $this->getRequest()->getPost("name");
        $size = (int)$this->getRequest()->getPost("size");

        if (!Strings::match($name, self::FILENAME_PATTERN)) {
            throw new CannotReceiveUploadedFileException(
                "File name '$name' contains invalid characters",
                IResponse::S400_BadRequest,
                FrontendErrorMappings::E400_003__UPLOADED_FILE_INVALID_CHARACTERS,
                ["filename" => $name, "pattern" => self::FILENAME_PATTERN]
            );
        }

        $maxSize = 1024 * 1024 * 1024;
        if ($size < 0 || $size > $maxSize) {
            // TODO: in the future, we might want to employ more sophisticated quota checking
            throw new CannotReceiveUploadedFileException(
                "Invalid declared file size ($size) for per-partes upload",
                IResponse::S400_BadRequest,
                FrontendErrorMappings::E400_004__UPLOADED_FILE_INVALID_SIZE,
                ["size" => $size, "maximum" => $maxSize]
            );
        }

        // create the partial file record in database
        $partialFile = new UploadedPartialFile($name, $size, $user);
        $this->uploadedPartialFiles->persist($partialFile);
        $this->uploadedPartialFiles->flush();
        $this->sendSuccessResponse($partialFile);
    }

    public function checkAppendPartial(string $id)
    {
        $file = $this->uploadedPartialFiles->findOrThrow($id);
        if (!$this->uploadedPartialFileAcl->canAppendPartial($file)) {
            throw new ForbiddenRequestException("You cannot add chunks to a per-partes upload started by another user");
        }
    }

    /**
     * Add another chunk to partial upload.
     * @PUT
     * @throws InvalidApiArgumentException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws CannotReceiveUploadedFileException
     * @throws InternalServerException
     */
    #[Query("offset", new VInt(), "Offset of the chunk for verification", required: true)]
    #[Path("id", new VUuid(), "Identifier of the partial file", required: true)]
    #[File(FileRequestType::OctetStream, "A chunk of the uploaded file", required: false)]
    public function actionAppendPartial(string $id, int $offset)
    {
        $partialFile = $this->uploadedPartialFiles->findOrThrow($id);

        if ($partialFile->getUploadedSize() !== $offset) {
            throw new InvalidApiArgumentException(
                'offset',
                "The offset must corresponds with the actual upload size of the partial file."
            );
        }

        try {
            // the store function takes the chunk directly from request body
            $size = $this->fileStorage->storeUploadedPartialFileChunk($partialFile);
        } catch (Exception $e) {
            throw new InternalServerException(
                "Cannot save a data chunk of per-partes upload",
                FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
                null,
                $e
            );
        }

        if ($size > 0) {
            $partialFile->addChunk($size);
            $this->uploadedPartialFiles->persist($partialFile);
            $this->uploadedPartialFiles->flush();
        }

        $this->sendSuccessResponse($partialFile);
    }

    public function checkCancelPartial(string $id)
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
    #[Path("id", new VUuid(), "Identifier of the partial file", required: true)]
    public function actionCancelPartial(string $id)
    {
        $partialFile = $this->uploadedPartialFiles->findOrThrow($id);
        try {
            $deletedFiles = $this->fileStorage->deleteUploadedPartialFileChunks($partialFile);
        } catch (Exception $e) {
            throw new InternalServerException(
                "Cannot save a data chunk of per-partes upload",
                FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
                null,
                $e
            );
        }

        if ($deletedFiles !== $partialFile->getChunks()) {
            $this->logger->log(
                sprintf(
                    "Per-partes upload was canceled, but only %d chunk files out of %d was deleted.",
                    $deletedFiles,
                    $partialFile->getChunks()
                ),
                ILogger::WARNING
            );
        }

        $this->uploadedPartialFiles->remove($partialFile);
        $this->uploadedPartialFiles->flush();
        $this->sendSuccessResponse("OK");
    }

    public function checkCompletePartial(string $id)
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
    #[Path("id", new VUuid(), "Identifier of the partial file", required: true)]
    public function actionCompletePartial(string $id)
    {
        $partialFile = $this->uploadedPartialFiles->findOrThrow($id);
        if (!$partialFile->isUploadComplete()) {
            throw new CannotReceiveUploadedFileException(
                "Unable to finalize incomplete per-partes upload.",
                IResponse::S400_BadRequest,
                FrontendErrorMappings::E400_005__UPLOADED_FILE_PARTIAL,
                [
                    "chunks" => $partialFile->getChunks(),
                    "totalSize" => $partialFile->getTotalSize(),
                    "uploadedSize" => $partialFile->getUploadedSize(),
                ]
            );
        }

        // create uploaded file entity from partial file data
        $uploadedFile = new UploadedFile(
            $partialFile->getName(),
            new DateTime(),
            $partialFile->getTotalSize(),
            $partialFile->getUser()
        );
        $this->uploadedFiles->persist($uploadedFile);

        // assemble chunks in file storage
        try {
            $this->fileStorage->assembleUploadedPartialFile($partialFile, $uploadedFile);
        } catch (Exception $e) {
            $this->uploadedFiles->remove($uploadedFile);
            $this->uploadedFiles->flush();

            $this->uploadedPartialFiles->remove($partialFile);
            $this->uploadedPartialFiles->flush();
            throw new InternalServerException(
                "Cannot concatenate data chunks of per-partes upload into uploaded file",
                FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
                null,
                $e
            );
        }

        // partial file no longer needed (and its file chunks has been removed)
        $this->uploadedPartialFiles->remove($partialFile);

        $this->uploadedFiles->flush();
        $this->uploadedPartialFiles->flush();
        $this->sendSuccessResponse($uploadedFile);
    }

    public function checkDownloadExerciseFile(string $id)
    {
        $file = $this->exerciseFiles->findOrThrow($id);
        if (!$this->uploadedFileAcl->canDownloadExerciseFile($file)) {
            throw new ForbiddenRequestException("You are not allowed to download file '{$file->getId()}");
        }
    }

    /**
     * Download exercise file
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws \Nette\Application\AbortException
     */
    #[Path("id", new VUuid(), "Identifier of the file", required: true)]
    public function actionDownloadExerciseFile(string $id)
    {
        $fileEntity = $this->exerciseFiles->findOrThrow($id);
        $file = $fileEntity->getFile($this->fileStorage);
        if (!$file) {
            throw new NotFoundException("Exercise file not found in the storage");
        }
        $this->sendStorageFileResponse($file, $fileEntity->getName());
    }
}
