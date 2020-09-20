<?php

namespace App\V1Module\Presenters;

use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\FileStorageManager;
use App\Helpers\UploadsConfig;
use App\Model\Repository\Assignments;
use App\Model\Repository\SupplementaryExerciseFiles;
use App\Model\Repository\UploadedFiles;
use App\Model\Entity\UploadedFile;
use App\Responses\StorageFileResponse;
use App\Security\ACL\IUploadedFilePermissions;
use ForceUTF8\Encoding;
use Nette\Application\Responses\FileResponse;
use Nette\Utils\Strings;
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
     * @var IUploadedFilePermissions
     * @inject
     */
    public $uploadedFileAcl;

    /**
     * @var SupplementaryExerciseFiles
     * @inject
     */
    public $supplementaryFiles;

    /**
     * @var UploadsConfig
     * @inject
     */
    public $uploadsConfig;

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
     * @param string $id Identifier of the uploaded file
     */
    public function actionDetail(string $id)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        $this->sendSuccessResponse($file);
    }

    public function checkDownload(string $id)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        if (!$this->uploadedFileAcl->canDownload($file)) {
            throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
        }
    }

    /**
     * Download a file
     * @GET
     * @param string $id Identifier of the file
     * @throws \Nette\Application\AbortException
     * @throws \Nette\Application\BadRequestException
     */
    public function actionDownload(string $id)
    {
        $fileEntity = $this->uploadedFiles->findOrThrow($id);
        $file = $fileEntity->getFile($this->fileStorage);
        if (!$file) {
            throw new NotFoundException("File not found in the storage");
        }
        $this->sendResponse(new StorageFileResponse($file, $fileEntity->getName()));
    }

    public function checkContent(string $id)
    {
        $file = $this->uploadedFiles->findOrThrow($id);
        if (!$this->uploadedFileAcl->canDownload($file)) {
            throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
        }
    }

    /**
     * Get the contents of a file
     * @GET
     * @param string $id Identifier of the file
     */
    public function actionContent(string $id)
    {
        $fileEntity = $this->uploadedFiles->findOrThrow($id);
        $file = $fileEntity->getFile($this->fileStorage);
        if (!$file) {
            throw new NotFoundException("File not found in the storage");
        }

        $sizeLimit = $this->uploadsConfig->getMaxPreviewSize();
        $contents = $file->getContents($sizeLimit);
        
        // Remove UTF BOM prefix...
        $utf8bom = "\xef\xbb\xbf";
        $contents = Strings::replace($contents, "~^$utf8bom~");

        $fixedContents = @mb_convert_encoding($contents, 'UTF-8', 'UTF-8');

        $this->sendSuccessResponse(
            [
                "content" => $fixedContents,
                "malformedCharacters" => $fixedContents !== $contents,
                "tooLarge" => $fileEntity->getFileSize() > $sizeLimit,
            ]
        );
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
     * @throws InvalidArgumentException for files with invalid names
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws CannotReceiveUploadedFileException
     * @throws InternalServerException
     */
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
            throw new CannotReceiveUploadedFileException($file->getName(), $file->getError());
        }

        if (!Strings::match($file->getName(), self::FILENAME_PATTERN)) {
            throw new CannotReceiveUploadedFileException($file->getName(), "File name contains invalid characters");
        }

        try {
            $uploadedFile = new UploadedFile($file->getName(), new DateTime(), $file->getSize(), $user);
            $this->fileStorage->storeUploadedFile($uploadedFile, $file);
        } catch (Exception $e) {
            throw new InternalServerException("Cannot move uploaded file to internal server storage");
        }

        $this->uploadedFiles->persist($uploadedFile);
        $this->uploadedFiles->flush();
        $this->sendSuccessResponse($uploadedFile);
    }

    public function checkDownloadSupplementaryFile(string $id)
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
        $fileEntity = $this->supplementaryFiles->findOrThrow($id);
        $file = $fileEntity->getFile($this->fileStorage);
        if (!$file) {
            throw new NotFoundException("Supplementary file not found in the storage");
        }
        $this->sendResponse(new StorageFileResponse($file, $fileEntity->getName()));
    }
}
