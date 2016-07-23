<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\UploadedFile;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Users;

class UploadedFilesPresenter extends BasePresenter {

  private $uploadedFiles;

  public function __construct(Users $users, UploadedFiles $files) {
    parent::__construct($users);
    $this->uploadedFiles = $files;
  }

  public function actionUpload() {
    if (!$this->getHttpRequest()->isMethod('POST')) {
      // @todo check for POST request only
      throw new \Exception;
    }

    // $userId = $this->user->id;
    $userId = '1fe2255e-50e2-11e6-beb8-9e71128cae77'; // @todo remove
    $user = $this->findUserOrThrow($userId);
    $files = $this->getHttpRequest()->getFiles();
    $file = array_pop($files);
    if (!$file) {
      // @todo throw an error
      throw new \Exception;
    }

    $uploadedFile = UploadedFile::upload($file, $user);
    if ($uploadedFile !== FALSE) {
      $this->uploadedFiles->persist($uploadedFile);
      $this->uploadedFiles->flush();
      $this->sendJson($uploadedFile);
    } else {
      throw new \Exception; // @todo
    }
  }

}
