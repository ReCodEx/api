<?php

namespace App\Helpers;

use App\Model\Entity\ExerciseFile;
use App\Model\Entity\User;
use App\Model\Entity\Exercise;
use DateTime;
use Nette;
use Nette\Http\FileUpload;

/**
 * Stores uploaded supplementary exercise files on fileserver
 */
class ExerciseFileStorage extends Nette\Object {

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
   * Save the file into fileserver and return database entity
   * @param FileUpload $file The file to be stored
   * @param User       $user User, who uploaded the file
   * @param Exercise   $exercise
   * @return ExerciseFile|NULL If the operation is not successful, NULL is returned
   */
  public function store(FileUpload $file, User $user, Exercise $exercise) {
    if (!$file->isOk()) {
      return NULL;
    }

    $result = current($this->fileServer->sendSupplementaryFiles([$file]));

    $exerciseFile = new ExerciseFile(
      $file->getName(),
      new DateTime(),
      $file->getSize(),
      basename($result),
      $result,
      $user,
      $exercise
    );

    return $exerciseFile;
  }
}
