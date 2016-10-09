<?php

namespace App\V1Module\Presenters;

use App;
use App\Exceptions\NotFoundException;
use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\BadRequestException;

use App\Model\Entity\UploadedFile;
use App\Helpers\UploadedFileStorage;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Users;

/**
 * @LoggedIn
 */
class UploadedFilesPresenter extends BasePresenter {

  /** @var UploadedFiles */
  private $uploadedFiles;

  /** @var UploadedFileStorage */
  private $fileStorage;

  public function __construct(Users $users, UploadedFiles $files, UploadedFileStorage $fileStorage) {
    parent::__construct();
    $this->uploadedFiles = $files;
    $this->fileStorage = $fileStorage;
  }

  /**
   * @param $id
   * @return UploadedFile
   * @throws NotFoundException
   */
  protected function findFileOrThrow($id) {
    $file = $this->uploadedFiles->get($id);
    if (!$file) {
      throw new NotFoundException("File $id");
    }

    return $file;
  }

  /**
   * @GET
   * @todo: Check if this user can access the file
   */
  public function actionDetail(string $id) {
    $file = $this->findFileOrThrow($id);
    $this->sendSuccessResponse($file);
  }

  /**
   * @GET
   * @todo: Check if this user can access the file
   */
  public function actionContent(string $id) {
    $file = $this->findFileOrThrow($id);
    $this->sendSuccessResponse($file->getContent());
  }


  /** @POST */
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
