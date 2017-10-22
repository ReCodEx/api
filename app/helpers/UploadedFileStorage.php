<?php

namespace App\Helpers;

use App\Exceptions\InvalidArgumentException;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use App\Exceptions\UploadedFileException;
use Nette;
use Nette\Http\FileUpload;
use Nette\Utils\Strings;

/**
 * Stores uploaded files in a configured directory
 */
class UploadedFileStorage extends Nette\Object {
  public const FILENAME_PATTERN = '#^[a-z0-9\- _\.]+$#i';

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
   * @param User $user User who uploaded the file
   * @return UploadedFile|NULL If the operation is not successful, NULL is returned
   * @throws InvalidArgumentException
   */
  public function store(FileUpload $file, User $user) {
    if (!$file->isOk()) {
      return NULL;
    }

    if (!Strings::contains($file->getName(), ".") || Strings::endsWith($file->getName(), ".")) {
      throw new InvalidArgumentException("file", "The file name must have a valid extension");
    }

    list($fileName, $fileExt) = explode(".", $file->getName(), 2);

    if (!Strings::match($fileName, self::FILENAME_PATTERN)
        || !Strings::match($fileExt, self::FILENAME_PATTERN)) {
      throw new InvalidArgumentException("file", "File name contains invalid characters");
    }

    try {
      $filePath = $this->getFilePath($user->getId(), $file);
      $file->move($filePath); // moving might fail with Nette\InvalidStateException if the user does not have sufficient rights to the FS
    } catch (Nette\InvalidStateException $e) {
      return NULL;
    }

    $uploadedFile = new UploadedFile(
      sprintf("%s.%s", $fileName, strtolower($fileExt)), new \DateTime(), $file->getSize(), $user, $filePath
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
    list($fileName, $ext) = $this->sanitizeNameAndExtension($file);
    $uniqueId = uniqid();
    return "{$this->uploadDir}/user_{$userId}/{$fileName}_{$uniqueId}.$ext";
  }

  protected function sanitizeNameAndExtension(FileUpload $file) {
    list($fileName, $ext) = explode(".", $file->getSanitizedName(), 2);
    return [$fileName, strtolower($ext)];
  }

  public function delete(UploadedFile $file) {
    if ($file->getLocalFilePath() !== NULL) {
      try {
        Nette\Utils\FileSystem::delete($file->getLocalFilePath());
      } catch (\Exception $e) {
        throw new UploadedFileException("File {$file->getName()} cannot be deleted", $e);
      }
    }
  }
}
