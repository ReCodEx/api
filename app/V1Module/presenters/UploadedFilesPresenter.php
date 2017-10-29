<?php

namespace App\V1Module\Presenters;

use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;

use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\FileServerProxy;
use App\Helpers\UploadedFileStorage;
use App\Model\Repository\Assignments;
use App\Model\Repository\SupplementaryExerciseFiles;
use App\Model\Repository\UploadedFiles;
use App\Responses\GuzzleResponse;
use App\Security\ACL\IUploadedFilePermissions;
use ForceUTF8\Encoding;
use Nette\Application\Responses\FileResponse;

/**
 * Endpoints for management of uploaded files
 */
class UploadedFilesPresenter extends BasePresenter {

  /**
   * @var UploadedFiles
   * @inject
   */
  public $uploadedFiles;

  /**
   * @var UploadedFileStorage
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
   * @var FileServerProxy
   * @inject
   */
  public $fileServerProxy;


  /**
   * Get details of a file
   * @GET
   * @LoggedIn
   * @param string $id Identifier of the uploaded file
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if (!$this->uploadedFileAcl->canViewDetail($file)) {
      throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }
    $this->sendSuccessResponse($file);
  }

  /**
   * Download a file
   * @GET
   * @param string $id Identifier of the file
   * @throws ForbiddenRequestException
   */
  public function actionDownload(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if (!$this->uploadedFileAcl->canDownload($file)) {
      throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }
    $this->sendResponse(new FileResponse($file->getLocalFilePath(), $file->getName()));
  }

  /**
   * Get the contents of a file
   * @GET
   * @param string $id Identifier of the file
   * @throws ForbiddenRequestException
   */
  public function actionContent(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if (!$this->uploadedFileAcl->canDownload($file)) {
      throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }

    $content = $file->getContent();
    $fixedContent = Encoding::toUTF8($content);

    $this->sendSuccessResponse([
      "content" => $fixedContent,
      "malformedCharacters" => $fixedContent !== $content
    ]);
  }


  /**
   * Upload a file
   * @POST
   * @throws InvalidArgumentException for files with invalid names
   */
  public function actionUpload() {
    if (!$this->uploadedFileAcl->canUpload()) {
      throw new ForbiddenRequestException();
    }

    $user = $this->getCurrentUser();
    $files = $this->getRequest()->getFiles();
    if (count($files) === 0) {
      throw new BadRequestException("No file was uploaded");
    } elseif (count($files) > 1) {
      throw new BadRequestException("Too many files were uploaded");
    }

    $file = array_pop($files);
    $uploadedFile = $this->fileStorage->store($file, $user);

    if ($uploadedFile === NULL) {
      throw new CannotReceiveUploadedFileException($file->getName());
    }

    $this->uploadedFiles->persist($uploadedFile);
    $this->uploadedFiles->flush();
    $this->sendSuccessResponse($uploadedFile);
  }

  /**
   * Download supplementary file
   * @GET
   * @param string $id Identifier of the file
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionDownloadSupplementaryFile(string $id) {
    $file = $this->supplementaryFiles->findOrThrow($id);
    if (!$this->uploadedFileAcl->canDownloadSupplementaryFile($file)) {
      throw new ForbiddenRequestException("You are not allowed to download file '{$file->getId()}");
    }

    $stream = $this->fileServerProxy->getResultArchiveStream($file->getFileServerPath());
    if ($stream === null) {
      throw new NotFoundException("Supplementary file '$id' not found on remote fileserver");
    }

    $this->sendResponse(new GuzzleResponse($stream, $file->getName()));
  }

}
