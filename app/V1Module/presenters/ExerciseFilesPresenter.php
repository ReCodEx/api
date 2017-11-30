<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\CannotReceiveUploadedFileException;
use App\Helpers\UploadedFileStorage;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\AdditionalExerciseFile;
use App\Model\Repository\AdditionalExerciseFiles;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Helpers\ExerciseFileStorage;
use App\Model\Repository\SupplementaryExerciseFiles;
use App\Model\Repository\UploadedFiles;
use App\Security\ACL\IExercisePermissions;
use Exception;
use Tracy\ILogger;

/**
 * Endpoints for exercise files manipulation
 * @LoggedIn
 */
class ExerciseFilesPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   * @var UploadedFiles
   * @inject
   */
  public $uploadedFiles;

  /**
   * @var SupplementaryExerciseFiles
   * @inject
   */
  public $supplementaryFiles;

  /**
   * @var AdditionalExerciseFiles
   * @inject
   */
  public $additionalFiles;

  /**
   * @var ExerciseFileStorage
   * @inject
   */
  public $supplementaryFileStorage;

  /**
   * @var UploadedFileStorage
   * @inject
   */
  public $uploadedFileStorage;

  /**
   * @var IExercisePermissions
   * @inject
   */
  public $exerciseAcl;


  /**
   * Associate supplementary files with an exercise and upload them to remote file server
   * @POST
   * @Param(type="post", name="files", description="Identifiers of supplementary files")
   * @param string $id identification of exercise
   * @throws BadRequestException
   * @throws CannotReceiveUploadedFileException
   * @throws ForbiddenRequestException
   */
  public function actionUploadSupplementaryFiles(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot update this exercise.");
    }

    $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
    $newSupplementaryFiles = [];
    $deletedFiles = [];
    $currentSupplementaryFiles = [];

    /** @var SupplementaryExerciseFile $file */
    foreach ($exercise->getSupplementaryEvaluationFiles() as $file) {
      $currentSupplementaryFiles[$file->getName()] = $file;
    }

    /** @var UploadedFile $file */
    foreach ($files as $file) {
      if (get_class($file) !== UploadedFile::class) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
      }

      if (array_key_exists($file->getName(), $currentSupplementaryFiles)) {
        $currentFile = $currentSupplementaryFiles[$file->getName()];
        $exercise->getSupplementaryEvaluationFiles()->removeElement($currentFile);
      }

      $newSupplementaryFiles[] = $exerciseFile = $this->supplementaryFileStorage->storeExerciseFile($file, $exercise);
      $this->uploadedFiles->persist($exerciseFile, FALSE);
      $this->uploadedFiles->remove($file, FALSE);
      $deletedFiles[] = $file;
    }

    $this->uploadedFiles->flush();

    /** @var UploadedFile $file */
    foreach ($deletedFiles as $file) {
      try {
        $this->uploadedFileStorage->delete($file);
      } catch (Exception $e) {
        $this->logger->log($e->getMessage(), ILogger::EXCEPTION);
      }
    }

    $this->sendSuccessResponse($exercise->getSupplementaryEvaluationFiles()->getValues());
  }

  /**
   * Get list of all supplementary files for an exercise
   * @GET
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetSupplementaryFiles(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canViewDetail($exercise)) {
      throw new ForbiddenRequestException("You cannot view supplementary files for this exercise.");
    }

    $this->sendSuccessResponse($exercise->getSupplementaryEvaluationFiles()->getValues());
  }

  /**
   * Delete supplementary exercise file with given id
   * @DELETE
   * @param string $id identification of exercise
   * @param string $fileId identification of file
   * @throws ForbiddenRequestException
   */
  public function actionDeleteSupplementaryFile(string $id, string $fileId) {
    $exercise = $this->exercises->findOrThrow($id);
    $file = $this->supplementaryFiles->findOrThrow($fileId);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot delete supplementary files for this exercise.");
    }

    $exercise->removeSupplementaryEvaluationFile($file);
    $this->exercises->flush();
    $this->sendSuccessResponse("OK");
  }

  /**
   * Associate additional exercise files with an exercise
   * @POST
   * @Param(type="post", name="files", description="Identifiers of additional files")
   * @param string $id identification of exercise
   * @throws BadRequestException
   * @throws CannotReceiveUploadedFileException
   * @throws ForbiddenRequestException
   */
  public function actionUploadAdditionalFiles(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot upload files for this exercise.");
    }

    $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
    $newAdditionalFiles = [];
    $currentAdditionalFiles = [];

    /** @var AdditionalExerciseFile $file */
    foreach ($exercise->getAdditionalFiles() as $file) {
      $currentAdditionalFiles[$file->getName()] = $file;
    }

    /** @var UploadedFile $file */
    foreach ($files as $file) {
      if (get_class($file) !== UploadedFile::class) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
      }

      if (array_key_exists($file->getName(), $currentAdditionalFiles)) {
        $currentFile = $currentAdditionalFiles[$file->getName()];
        $exercise->getAdditionalFiles()->removeElement($currentFile);
      }

      $newAdditionalFiles[] = $exerciseFile = AdditionalExerciseFile::fromUploadedFile($file, $exercise);
      $this->uploadedFiles->persist($exerciseFile, FALSE);
      $this->uploadedFiles->remove($file, FALSE);
    }

    $this->uploadedFiles->flush();
    $this->sendSuccessResponse($exercise->getAdditionalFiles()->getValues());
  }

  /**
   * Get a list of all additional files for an exercise
   * @GET
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetAdditionalFiles(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot view additional files for this exercise.");
    }

    $this->sendSuccessResponse($exercise->getAdditionalFiles()->getValues());
  }

  /**
   * Delete additional exercise file with given id
   * @DELETE
   * @param string $id identification of exercise
   * @param string $fileId identification of file
   * @throws ForbiddenRequestException
   */
  public function actionDeleteAdditionalFile(string $id, string $fileId) {
    $exercise = $this->exercises->findOrThrow($id);
    $file = $this->additionalFiles->findOrThrow($fileId);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot delete additional files for this exercise.");
    }

    $exercise->removeAdditionalFile($file);
    $this->exercises->flush();
    $this->sendSuccessResponse("OK");
  }

}
