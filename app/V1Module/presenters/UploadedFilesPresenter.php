<?php

namespace App\V1Module\Presenters;

use App\Exception\WrongHttpMethodException;
use App\Exception\CannotReceiveUploadedFileException;
use App\Exception\BadRequestException;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Users;

/**
 * @LoggedIn
 */
class UploadedFilesPresenter extends BasePresenter {

  private $uploadedFiles;

  public function __construct(Users $users, UploadedFiles $files) {
    parent::__construct($users);
    $this->uploadedFiles = $files;
  }

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


  /**
   * @POST
   */
  public function actionUpload() {
    $user = $this->findUserOrThrow("me");
    $files = $this->getHttpRequest()->getFiles();
    if (count($files) === 0) {
      throw new BadRequestException("No file was uploaded");
    } elseif (count($files) > 1) {
      throw new BadRequestException("Too many files were uploaded");
    }

    $file = array_pop($files);
    $uploadedFile = UploadedFile::upload($file, $user);
    if ($uploadedFile !== FALSE) {
      $this->uploadedFiles->persist($uploadedFile);
      $this->uploadedFiles->flush();
      $this->sendSuccessResponse($uploadedFile);
    } else {
      throw new CannotReceiveUploadedFileException($file->getSanitizedName());
    }
  }

}
