<?php

namespace App\Helpers;

use App\Exceptions\InvalidArgumentException;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use App\Exceptions\UploadedFileException;
use DateTime;
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

    if (!Strings::startsWith($file->getName(), ".") && Strings::contains($file->getName(), ".")) {
      list($fileName, $fileExt) = explode(".", $file->getName(), 2);
    } else {
      list($fileName, $fileExt) = [$file->getName(), NULL];
    }

    if (!Strings::match($fileName, self::FILENAME_PATTERN)
        || ($fileExt != NULL && !Strings::match($fileExt, self::FILENAME_PATTERN))) {
      throw new InvalidArgumentException("file", "File name contains invalid characters");
    }

    try {
      $filePath = $this->getFilePath($user->getId(), $fileName, $fileExt);
      $file->move($filePath); // moving might fail with Nette\InvalidStateException if the user does not have sufficient rights to the FS
    } catch (Nette\InvalidStateException $e) {
      return NULL;
    }

    $uploadedFileName = $fileExt !== NULL ? sprintf("%s.%s", $fileName, strtolower($fileExt)) : $fileName;
    $uploadedFile = new UploadedFile($uploadedFileName, new DateTime(), $file->getSize(), $user, $filePath);

    return $uploadedFile;
  }

  /**
   * For given user ID and file, get the path, where the file will be stored
   * @param string $userId User's identifier
   * @param $fileName
   * @param $ext
   * @return string Path, where the newly stored file will be saved (including configured uploadDir)
   */
  protected function getFilePath($userId, $fileName, $ext = NULL): string {
    $uniqueId = uniqid();

    if ($ext !== null) {
      $ext = strtolower($ext);
      $path = "{$fileName}_{$uniqueId}.{$ext}";
    } else {
      $path = "{$fileName}_{$uniqueId}";
    }

    return "{$this->uploadDir}/user_{$userId}/{$path}";
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
