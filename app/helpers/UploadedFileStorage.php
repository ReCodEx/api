<?php

namespace App\Helpers;

use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use Nette;
use Nette\Http\FileUpload;

/**
 * Stores uploaded files in a configured directory
 * @package App\Model
 */
class UploadedFileStorage extends Nette\Object {

  /** @var string */
  private $uploadDir;

  public function __construct(string $uploadDir) {
    $this->uploadDir = $uploadDir;
  }

  public function store(FileUpload $file, User $user) {
    if (!$file->isOk()) {
      return NULL;
    }

    try {
      $filePath = self::getFilePath($user->getId(), $file);
      $file->move($filePath); // moving might fail with Nette\InvalidStateException if the user does not have sufficient rights to the FS
    } catch (Nette\InvalidStateException $e) {
      return NULL;
    }

    $uploadedFile = new UploadedFile(
      $filePath,
      $file->getSanitizedName(),
      new \DateTime(),
      $file->getSize(),
      $user
    );

    return $uploadedFile;
  }

  protected function getFilePath($userId, FileUpload $file) {
    $fileName = pathinfo($file->getSanitizedName(), PATHINFO_FILENAME);
    $ext = pathinfo($file->getSanitizedName(), PATHINFO_EXTENSION);
    $uniqueId = uniqid();
    return "{$this->uploadDir}/user_{$userId}/{$fileName}_{$uniqueId}.$ext";
  }
}
