<?php

namespace App\Helpers;

use App\Exceptions\SubmissionFailedException;
use App\Model\Entity\Pipeline;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\Exercise;
use Nette;
use Nette\Http\FileUpload;

/**
 * Stores uploaded supplementary exercise files on fileserver
 */
class ExerciseFileStorage {
  use Nette\SmartObject;

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
   * @param Exercise $exercise
   * @return SupplementaryExerciseFile|NULL If the operation is not successful, NULL is returned
   * @throws SubmissionFailedException
   */
  public function storeExerciseFile(UploadedFile $file, Exercise $exercise) {
    $result = current($this->fileServer->sendSupplementaryFiles([$file]));
    $exerciseFile = SupplementaryExerciseFile::fromUploadedFileAndExercise($file, $exercise, basename($result), $result);

    return $exerciseFile;
  }

  /**
   * Save the file into fileserver and return database entity
   * @param UploadedFile|FileUpload $file The file to be stored
   * @param Pipeline $pipeline
   * @return SupplementaryExerciseFile|NULL If the operation is not successful, NULL is returned
   * @throws SubmissionFailedException
   */
  public function storePipelineFile(UploadedFile $file, Pipeline $pipeline) {
    $result = current($this->fileServer->sendSupplementaryFiles([$file]));
    $exerciseFile = SupplementaryExerciseFile::fromUploadedFileAndPipeline($file, $pipeline, basename($result), $result);

    return $exerciseFile;
  }
}
