<?php

namespace App\Helpers;

use App\Model\Entity\User;
use App\Model\Entity\Assignment;
use App\Exceptions\JobConfigStorageException;
use Nette;
use Nette\Http\FileUpload;

/**
 *
 */
class UploadedJobConfigStorage {

  const DEFAULT_MKDIR_MODE = 0777;

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
      $filePath = $this->getFilePathFromUpload($user->getId(), $file);
      $file->move($filePath); // moving might fail with Nette\InvalidStateException if the user does not have sufficient rights to the FS
      return $filePath;
    } catch (Nette\InvalidStateException $e) {
      return NULL;
    }
  }

  /**
   * Store given content into job config file.
   * @param string $content content of file which should be stored
   * @param User $user
   * @return string|NULL Path to newly stored file
   */
  public function storeContent(string $content, User $user) {
    $filePath = $this->getFilePath($user->getId());
    @mkdir(dirname($filePath), self::DEFAULT_MKDIR_MODE, TRUE);
    if (!file_put_contents($filePath, $content)) {
      return NULL;
    }

    return $filePath;
  }

  public function copyToUserAndUpdateRuntimeConfigs(Assignment $assignment, User $user) {
    foreach ($assignment->getSolutionRuntimeConfigs() as $config) {
      $filePath = $this->getFilePath($user->getId(), $config->getJobConfigFilePath());
      @mkdir(dirname($filePath), self::DEFAULT_MKDIR_MODE, TRUE);
      if (!@copy($config->getJobConfigFilePath(), $filePath)) {
        throw new JobConfigStorageException;
      }
      $config->setJobConfigFilePath($filePath);
    }
  }

  /**
   * Return storage file path for given information.
   * @param string $userId
   * @param string $fileName
   * @return string Path, where the newly stored file will be saved (including configured uploadDir)
   */
  protected function getFilePath($userId, $fileName = "job-config.yml"): string {
    $fileNameOnly = pathinfo($fileName, PATHINFO_FILENAME);
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueId = uniqid();
    return "{$this->uploadDir}/user_{$userId}/{$fileNameOnly}_{$uniqueId}.$ext";
  }

  /**
   * For given user ID and file, get the path, where the file will be stored
   * @param string     $userId User's identifier
   * @param FileUpload $file   File to be stored
   * @return string Path, where the newly stored file will be saved (including configured uploadDir)
   */
  protected function getFilePathFromUpload($userId, FileUpload $file): string {
    return $this->getFilePath($userId, $file->getSanitizedName());
  }

}
