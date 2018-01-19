<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SubmissionFailedException;
use App\Helpers\ExerciseConfig\ExerciseConfigChecker;
use App\Helpers\ExerciseRestrictionsConfig;
use App\Helpers\UploadedFileStorage;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\AttachmentFile;
use App\Model\Repository\AttachmentFiles;
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
   * @var AttachmentFiles
   * @inject
   */
  public $attachmentFiles;

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
   * @var ExerciseRestrictionsConfig
   * @inject
   */
  public $restrictionsConfig;

  /**
   * @var ExerciseConfigChecker
   * @inject
   */
  public $configChecker;

  /**
   * Associate supplementary files with an exercise and upload them to remote file server
   * @POST
   * @Param(type="post", name="files", description="Identifiers of supplementary files")
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws SubmissionFailedException
   */
  public function actionUploadSupplementaryFiles(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot update this exercise.");
    }

    $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
    $deletedFiles = [];
    $currentSupplementaryFiles = [];
    $totalFileSize = 0;

    /** @var SupplementaryExerciseFile $file */
    foreach ($exercise->getSupplementaryEvaluationFiles() as $file) {
      $currentSupplementaryFiles[$file->getName()] = $file;
      $totalFileSize += $file->getFileSize();
    }

    $totalFileCount = count($exercise->getSupplementaryEvaluationFiles());

    /** @var UploadedFile $file */
    foreach ($files as $file) {
      if (get_class($file) !== UploadedFile::class) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
      }

      if (array_key_exists($file->getName(), $currentSupplementaryFiles)) {
        /** @var SupplementaryExerciseFile $currentFile */
        $currentFile = $currentSupplementaryFiles[$file->getName()];
        $exercise->getSupplementaryEvaluationFiles()->removeElement($currentFile);
        $totalFileSize -= $currentFile->getFileSize();
      } else {
        $totalFileCount += 1;
      }

      $totalFileSize += $file->getFileSize();
    }

    $fileCountLimit = $this->restrictionsConfig->getSupplementaryFileCountLimit();
    if ($totalFileCount > $fileCountLimit) {
      throw new InvalidArgumentException(
        "files",
        "The number of files would exceed the configured limit ($fileCountLimit)"
      );
    }

    $sizeLimit = $this->restrictionsConfig->getSupplementaryFileSizeLimit();
    if ($totalFileSize > $sizeLimit) {
      throw new InvalidArgumentException(
        "files",
        "The total size of files would exceed the configured limit ($sizeLimit)"
      );
    }

    /** @var UploadedFile $file */
    foreach ($files as $file) {
      $exerciseFile = $this->supplementaryFileStorage->storeExerciseFile($file, $exercise);
      $this->uploadedFiles->persist($exerciseFile, FALSE);
      $this->uploadedFiles->remove($file, FALSE);
      $deletedFiles[] = $file;
    }

    $exercise->updatedNow();
    $this->exercises->flush();
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

    $exercise->updatedNow();
    $exercise->removeSupplementaryEvaluationFile($file);
    $this->exercises->flush();

    $this->configChecker->check($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse("OK");
  }

  /**
   * Associate attachment exercise files with an exercise
   * @POST
   * @Param(type="post", name="files", description="Identifiers of attachment files")
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionUploadAttachmentFiles(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot upload files for this exercise.");
    }

    $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
    $currentAttachmentFiles = [];

    /** @var AttachmentFile $file */
    foreach ($exercise->getAttachmentFiles() as $file) {
      $currentAttachmentFiles[$file->getName()] = $file;
    }

    /** @var UploadedFile $file */
    foreach ($files as $file) {
      if (get_class($file) !== UploadedFile::class) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
      }

      if (array_key_exists($file->getName(), $currentAttachmentFiles)) {
        $currentFile = $currentAttachmentFiles[$file->getName()];
        $exercise->getAttachmentFiles()->removeElement($currentFile);
      }

      $exerciseFile = AttachmentFile::fromUploadedFile($file, $exercise);
      $this->uploadedFiles->persist($exerciseFile, FALSE);
      $this->uploadedFiles->remove($file, FALSE);
    }

    $exercise->updatedNow();
    $this->exercises->flush();
    $this->uploadedFiles->flush();
    $this->sendSuccessResponse($exercise->getAttachmentFiles()->getValues());
  }

  /**
   * Get a list of all attachment files for an exercise
   * @GET
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetAttachmentFiles(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot view attachment files for this exercise.");
    }

    $this->sendSuccessResponse($exercise->getAttachmentFiles()->getValues());
  }

  /**
   * Delete attachment exercise file with given id
   * @DELETE
   * @param string $id identification of exercise
   * @param string $fileId identification of file
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionDeleteAttachmentFile(string $id, string $fileId) {
    $exercise = $this->exercises->findOrThrow($id);
    $file = $this->attachmentFiles->findOrThrow($fileId);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot delete attachment files for this exercise.");
    }

    $exercise->updatedNow();
    $exercise->removeAttachmentFile($file);
    $this->exercises->flush();
    $this->sendSuccessResponse("OK");
  }

}
