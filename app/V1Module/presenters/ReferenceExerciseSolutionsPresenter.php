<?php

namespace App\V1Module\Presenters;

use App\Helpers\MonitorConfig;
use App\Model\Entity\SolutionRuntimeConfig;
use App\Model\Repository\Exercises;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\ReferenceSolutionEvaluations;
use App\Model\Repository\UploadedFiles;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionEvaluation;

use App\Helpers\JobConfig;
use App\Helpers\SubmissionHelper;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
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
   * @var MonitorConfig
   * @inject
   */
  public $monitorConfig;

  /**
   * @var JobConfig\Storage
   * @inject
   */
  public $jobConfigs;

  /**
   * Get reference solutions for an exercise
   * @GET
   * @UserIsAllowed(exercises="view-detail")
   */
  public function actionExercise(string $id) {
    // @todo check that this user can access this information
    $exercise = $this->findExerciseOrThrow($id);
    $this->sendSuccessResponse($exercise->referenceSolutions->getValues());
  }

  /**
   * Add new reference solution to existing exercise
   * @POST
   * @Param(type="post", name="note", validation="string", description="Description of this particular reference solution, for example used algorithm")
   * @Param(type="post", name="files", description="Files of the reference solution")
   * @Param(type="post", name="runtime", description="ID of runtime for this solution")
   * @UserIsAllowed(exercises="create")
   */
  public function actionCreateReferenceSolution(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$exercise->isAuthor($user)) {
      throw new ForbiddenRequestException("Only author can create reference assignments");
    }

    $req = $this->getHttpRequest();
    $files = $this->files->findAllById($req->getPost("files"));
    $note = $req->getPost("note");
    $runtimeId = $req->getPost("runtime");

    $criteria = Criteria::create()->where(Criteria::expr()->eq("id", $runtimeId));
    $configsFound = $exercise->getSolutionRuntimeConfigs()->matching($criteria);
    $numOfResults = $configsFound->count();
    if ($numOfResults !== 1) {
      throw new NotFoundException("Runtime config ID not specified correctly. Got ${numOfResults} matches from exercise runtimes.");
    }
    $runtime = $configsFound->first();

    $solution = new ReferenceExerciseSolution($exercise, $user, $note, $files, $runtime);
    $this->referenceSolutions->persist($solution);

    $this->sendSuccessResponse($solution);
  }

  /**
   * Evaluate reference solutions to an exercise for a hardware group
   * @POST
   * @Param(type="post", name="hwGroup", description="Identififer of a hardware group")
   * @UserIsAllowed(assignments="create")
   */
  public function actionEvaluate(string $exerciseId, string $id) {
    $referenceSolution = $this->referenceSolutions->findOrThrow($id);
    
    if ($referenceSolution->getExercise()->getId() !== $exerciseId) {
      throw new SubmissionFailedException("The reference solution '$id' does not belong to exercise '$exerciseId'");
    }

    // create the entity and generate the ID
    $hwGroup = $this->getHttpRequest()->getPost("hwGroup");
    $evaluation = new ReferenceSolutionEvaluation($referenceSolution, $hwGroup);
    $this->referenceEvaluations->persist($evaluation);

    /** @var SolutionRuntimeConfig $runtimeConfig */
    $runtimeConfig = $referenceSolution->getSolution()->getSolutionRuntimeConfig();

    // configure the job and start evaluation
    $jobConfig = $this->jobConfigs->getJobConfig($runtimeConfig->getJobConfigFilePath());
    $jobConfig->setJobId(ReferenceSolutionEvaluation::JOB_TYPE, $evaluation->getId());
    $files = $referenceSolution->getFiles()->getValues();

    $resultsUrl = $this->submissionHelper->initiateEvaluation(
      $jobConfig,
      $files,
      ['env' => $runtimeConfig->runtimeEnvironment->id],
      $hwGroup
    );

    if($resultsUrl !== NULL) {
      $evaluation->setResultsUrl($resultsUrl);
      $this->referenceEvaluations->flush();
      $this->sendSuccessResponse([
        "evaluation" => $evaluation,
        "webSocketChannel" => [
          "id" => $jobConfig->getJobId(),
          "monitorUrl" => $this->monitorConfig->getAddress(),
          "expectedTasksCount" => $jobConfig->getTasksCount()
        ],
      ]);
    } else {
      throw new SubmissionFailedException;
    }
  }
}
