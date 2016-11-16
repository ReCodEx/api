<?php

namespace App\Helpers;

use App\Model\Entity\SupplementaryFile;
use App\Model\Entity\User;
use App\Model\Entity\Exercise;
use Nette;
use Nette\Http\FileUpload;

/**
 * Stores uploaded files in a configured directory
 */
class SupplementaryFileStorage extends Nette\Object {

  /**
   * @var FileServerProxy
   */
  private $fileServer;

  /**
   * Constructor
   * @param FileServerProxy $fileServer
   */
  public function __construct(FileServerProxy $fileServer) {
    $this->fileServer = $fileServer;
  }

  /**
   * Save the file into fileserver
   * @param FileUpload $file The file to be stored
   * @param User       $user User, who uploaded the file
   * @param Exercise   $exercise
   * @return SupplementaryFile|NULL If the operation is not successful, NULL is returned
   */
  public function store(FileUpload $file, User $user, Exercise $exercise) {
    if (!$file->isOk()) {
      return NULL;
    }

    $result = current($this->fileServer->sendSupplementaryFiles([$file]));

    $supplementaryFile = new SupplementaryFile(
      $file->getName(),
      basename($result),
      $result,
      $file->getSize(),
      $user,
      $exercise
    );

    return $supplementaryFile;
  }
}
