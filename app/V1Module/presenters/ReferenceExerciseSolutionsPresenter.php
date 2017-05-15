<?php

namespace App\V1Module\Presenters;

use App\Exceptions\NotReadyException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\FileServerProxy;
use App\Helpers\MonitorConfig;
use App\Helpers\JobConfig;
use App\Helpers\SubmissionHelper;
use App\Model\Entity\Exercise;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\RuntimeConfig;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Model\Repository\Exercises;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\ReferenceSolutionEvaluations;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;

use App\Responses\GuzzleResponse;
use Doctrine\Common\Collections\Criteria;

/**
 * Endpoints for manipulation of reference solutions of exercises
 */
class ReferenceExerciseSolutionsPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   * @var UploadedFiles
   * @inject
   */
  public $files;

  /**
   * @var ReferenceExerciseSolutions
   * @inject
   */
  public $referenceSolutions;

  /**
   * @var ReferenceSolutionEvaluations
   * @inject
   */
  public $referenceEvaluations;

  /**
   * @var SubmissionHelper
   * @inject
   */
  public $submissionHelper;

  /**
   * @var JobConfig\Storage
   * @inject
   */
  public $jobConfigs;

  /**
   * @var HardwareGroups
   * @inject
   */
  public $hardwareGroups;

  /**
   * @var FileServerProxy
   * @inject
   */
  public $fileServerProxy;

  /**
   * @var RuntimeEnvironments
   * @inject
   */
  public $runtimeEnvironments;

  /**
   * Get reference solutions for an exercise
   * @GET
   * @UserIsAllowed(exercises="view-detail")
   * @throws NotFoundException
   * @param string $exerciseId Identifier of the exercise
   */
  public function actionExercise(string $exerciseId) {
    // @todo check that this user can access this information
    $exercise = $this->exercises->findOrThrow($exerciseId);
    if (!$exercise->canAccessDetail($this->getCurrentUser())) {
      throw new NotFoundException;
    }

    $this->sendSuccessResponse($exercise->referenceSolutions->getValues());
  }

  /**
   * Add new reference solution to an exercise
   * @POST
   * @Param(type="post", name="note", validation="string", description="Description of this particular reference solution, for example used algorithm")
   * @Param(type="post", name="files", description="Files of the reference solution")
   * @Param(type="post", name="runtimeEnvironmentId", description="ID of runtime for this solution")
   * @UserIsAllowed(exercises="create")
   * @param string $exerciseId Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionCreateReferenceSolution(string $exerciseId) {
    $exercise = $this->exercises->findOrThrow($exerciseId);
    $user = $this->getCurrentUser();

    if (!$exercise->isAuthor($user)) {
      throw new ForbiddenRequestException("Only the exercise author can create reference solutions");
    }

    $req = $this->getRequest();
    $note = $req->getPost("note");
    $runtimeId = $req->getPost("runtimeEnvironmentId");

    // detect the runtime configuration
    if ($runtimeId !== NULL) {
      $runtimeEnvironment = $this->runtimeEnvironments->findOrThrow($runtimeId);
      $runtimeConfiguration = $exercise->getRuntimeConfigByEnvironment($runtimeEnvironment);
      if ($runtimeConfiguration === NULL) {
        throw new NotFoundException("RuntimeConfiguration was not found");
      }
    } else {
      throw new NotFoundException("RuntimeConfiguration was not found - automatic detection is not supported");
    }

    $referenceSolution = new ReferenceExerciseSolution($exercise, $user, $note, $runtimeConfiguration);

    $uploadedFiles = $this->files->findAllById($req->getPost("files"));
    if (count($uploadedFiles) === 0) {
      throw new SubmissionEvaluationFailedException("No files were uploaded");
    }

    foreach ($uploadedFiles as $file) {
      if (!($file instanceof UploadedFile)) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used in a different submission.");
      }

      $solutionFile = SolutionFile::fromUploadedFile($file, $referenceSolution->getSolution());
      $this->files->persist($solutionFile, FALSE);
      $this->files->remove($file, FALSE);
    }

    $this->referenceSolutions->persist($referenceSolution);
    $this->sendSuccessResponse($referenceSolution);
  }

  /**
   * Evaluate a single reference exercise solution for all configured hardware groups
   * @POST
   * @UserIsAllowed(assignments="create")
   * @param string $id Identifier of the reference solution
   * @throws NotFoundException
   */
  public function actionEvaluate(string $id) {
    /** @var ReferenceExerciseSolution $referenceSolution */
    $referenceSolution = $this->referenceSolutions->findOrThrow($id);

    /** @var RuntimeConfig $runtimeConfig */
    list($evaluations, $errors) = $this->evaluateReferenceSolution($referenceSolution);

    $this->sendSuccessResponse([
      "referenceSolution" => $referenceSolution,
      "evaluations" => $evaluations,
      "errors" => $errors
    ]);
  }

  /**
   * Evaluate all reference solutions for an exercise (and for all configured hardware groups).
   * @POST
   * @UserIsAllowed(assignments="create")
   * @throws SubmissionFailedException
   * @param string $exerciseId Identifier of the exercise
   */
  public function actionEvaluateForExercise($exerciseId) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($exerciseId);
    $result = [];

    foreach ($exercise->getReferenceSolutions() as $referenceSolution) {
      list($evaluations, $errors) = $this->evaluateReferenceSolution($referenceSolution);
      $result[] = [
        "referenceSolution" => $referenceSolution,
        "evaluations" => $evaluations,
        "errors" => $errors
      ];
    }

    $this->sendSuccessResponse($result);
  }

  private function evaluateReferenceSolution(ReferenceExerciseSolution $referenceSolution): array {
    $runtimeConfig = $referenceSolution->getRuntimeConfig();
    $jobConfig = $this->jobConfigs->getJobConfig($runtimeConfig->getJobConfigFilePath());
    $hwGroups = $jobConfig->getHardwareGroups();
    $evaluations = [];
    $errors = [];

    foreach ($hwGroups as $hwGroup) {
      // create the entity and generate the ID
      $evaluation = new ReferenceSolutionEvaluation($referenceSolution, $this->hardwareGroups->findOrThrow($hwGroup));
      $this->referenceEvaluations->persist($evaluation);

      // configure the job and start evaluation
      $jobConfig->getSubmissionHeader()->setId($evaluation->getId())->setType(ReferenceSolutionEvaluation::JOB_TYPE);
      $files = $referenceSolution->getFiles()->getValues();

      try {
        $resultsUrl = $this->submissionHelper->initiateEvaluation(
          $jobConfig,
          $files,
          ['env' => $runtimeConfig->getRuntimeEnvironment()->getId()],
          $hwGroup
        );
        $evaluation->setResultsUrl($resultsUrl);
        $this->referenceEvaluations->flush();
        $evaluations[] = $evaluation;
      } catch (SubmissionFailedException $e) {
        $errors[] = $hwGroup;
      }
    }

    return [$evaluations, $errors];
  }

  /**
   * Download result archive from backend for a reference solution evaluation
   * @GET
   * @UserIsAllowed(assignments="create")
   * @param string $evaluationId
   * @throws NotReadyException
   * @throws ForbiddenRequestException
   */
  public function actionDownloadResultArchive(string $evaluationId) {
    /** @var ReferenceSolutionEvaluation $evaluation */
    $evaluation = $this->referenceEvaluations->findOrThrow($evaluationId);

    if (!$evaluation->hasEvaluation()) {
      throw new NotReadyException("Submission is not evaluated yet");
    }

    $stream = $this->fileServerProxy->getResultArchiveStream($evaluation->getResultsUrl());
    $this->sendResponse(new GuzzleResponse($stream, $evaluationId . '.zip'));
  }
}
