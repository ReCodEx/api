<?php

namespace App\Helpers;

use App\Model\Entity\User;
use Nette;
use Nette\Http\FileUpload;

/**
 *
 */
class JobConfigStorage {

  /**
   * Target directory, where the files will be stored
   * @var string
   */
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
   * @return string|NULL Path to newly stored file
   */
  public function store(FileUpload $file, User $user) {
    if (!$file->isOk()) {
      return NULL;
    }

    try {
      $filePath = getFilePath($user->getId(), $file);
      $file->move($filePath); // moving might fail with Nette\InvalidStateException if the user does not have sufficient rights to the FS
      return $filePath;
    } catch (Nette\InvalidStateException $e) {
      return NULL;
    }
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
}
