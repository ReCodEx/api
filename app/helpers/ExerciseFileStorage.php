<?php

namespace App\Helpers;

use App\Model\Entity\Pipeline;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
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
   * @param UploadedFile|FileUpload $file The file to be stored
   * @param Exercise|null $exercise
   * @param Pipeline|null $pipeline
   * @return SupplementaryExerciseFile|NULL If the operation is not successful, NULL is returned
   * @internal param User $user User, who uploaded the file
   */
  public function store(UploadedFile $file, Exercise $exercise = null, Pipeline $pipeline = null) {
    $result = current($this->fileServer->sendSupplementaryFiles([$file]));
    $exerciseFile = SupplementaryExerciseFile::fromUploadedFile($file, $exercise, $pipeline, basename($result), $result);

    return $exerciseFile;
  }
}
