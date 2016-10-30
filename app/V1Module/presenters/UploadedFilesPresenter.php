<?php

namespace App\V1Module\Presenters;

use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;

use App\Helpers\UploadedFileStorage;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use App\Model\Repository\UploadedFiles;
use Nette\Application\Responses\FileResponse;

/**
 * Endpoints for management of uploaded files
 * @LoggedIn
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
   *
   * @param UploadedFile $file
   * @throws ForbiddenRequestException
   */
  private function throwIfUserCantAccessFile(UploadedFile $file) {
    $user = $this->users->findCurrentUserOrThrow();
    if ($file->getUser()->getId() !== $user->getId()) { // @todo the admins and supervisors of the group, into which the assignment for which this file was submitted as solution should be able to access this file
      throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }
  }

  /**
   * Get details of a file
   * @GET
   * @UserIsAllowed(files="view-detail")
   * @todo: Check if this user can access the file
   */
  public function actionDetail(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    $this->throwIfUserCantAccessFile($file);
    $file->setDownloadUrl($this->link("//download", $id));
    $this->sendSuccessResponse($file);
  }

  /**
   * Download a file
   * @GET
   * @UserIsAllowed(files="view-detail")
   * @todo: Check if this user can access the file
   */
  public function actionDownload(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    $this->throwIfUserCantAccessFile($file);
    $this->send(new FileResponse($file->getFilePath(), $file->getName()));
  }

  /**
   * Get the contents of a file
   * @GET
   * @UserIsAllowed(files="view-content")
   * @todo: Check if this user can access the file
   */
  public function actionContent(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    $this->sendSuccessResponse($file->getContent());
  }


  /**
   * Upload a file
   * @POST
   * @UserIsAllowed(files="upload")
   */
  public function actionUpload() {
    $user = $this->users->findCurrentUserOrThrow();
    $files = $this->getHttpRequest()->getFiles();
    if (count($files) === 0) {
      throw new BadRequestException("No file was uploaded");
    } elseif (count($files) > 1) {
      throw new BadRequestException("Too many files were uploaded");
    }

    $file = array_pop($files);
    $uploadedFile = $this->fileStorage->store($file, $user);
    if ($uploadedFile !== NULL) {
      $this->uploadedFiles->persist($uploadedFile);
      $this->uploadedFiles->flush();
      $this->sendSuccessResponse($uploadedFile);
    } else {
      throw new CannotReceiveUploadedFileException($file->getSanitizedName());
    }
  }

}
