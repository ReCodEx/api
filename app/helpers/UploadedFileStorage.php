<?php

namespace App\Helpers;

use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use Nette;
use Nette\Http\FileUpload;

/**
 * Stores uploaded files in a configured directory
 */
class UploadedFileStorage extends Nette\Object {

  /** @var string Target directory, where the files will be stored */
  private $uploadDir;

  /**
   * Constructor
   * @param string $uploadDir Target storage directory
   */
  public function __construct(string $uploadDir) {
    $this->uploadDir = $uploadDir;
  }

  /**
   * Save the file into storage
   * @param FileUpload $file The file to be stored
   * @param User       $user User, who uploaded the file
   * @return UploadedFile|NULL If the operation is not successful, NULL is returned
   */
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
      $file->getName(), new \DateTime(), $file->getSize(), $user, $filePath
    );

    return $uploadedFile;
  }

  /**
   * For given user ID and file, get the path, where the file will be stored
   * @param string     $userId User's identifier
   * @param FileUpload $file   File to be stored
   * @return string Path, where the newly stored file will be saved (including configured uploadDir)
   */
  protected function getFilePath($userId, FileUpload $file): string {
    $fileName = pathinfo($file->getSanitizedName(), PATHINFO_FILENAME);
    $ext = pathinfo($file->getSanitizedName(), PATHINFO_EXTENSION);
    $uniqueId = uniqid();
    return "{$this->uploadDir}/user_{$userId}/{$fileName}_{$uniqueId}.$ext";
  }

  public function delete(UploadedFile $file) {
    if ($file->getLocalFilePath() !== NULL) {
      Nette\Utils\FileSystem::delete($file);
    }
  }
}
