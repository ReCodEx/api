<?php

namespace App\V1Module\Presenters;

use App\Exceptions\NotFoundException;
use App\Model\Repository\Exercises;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\ReferenceSolutionEvaluations;
use App\Model\Repository\UploadedFiles;
use App\Model\Entity\Exercise;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionEvaluation;

use App\Helpers\JobConfig;
use App\Helpers\SubmissionHelper;

use Nette\Utils\Arrays;

class ReferenceExerciseSolutionsPresenter extends BasePresenter {

  /** @inject @var Exercises */
  public $exercises;

  /** @inject @var UploadedFiles */
  public $files;

  /** @inject @var ReferenceExerciseSolutions */
  public $referenceSolutions;

  /** @inject @var ReferenceSolutionEvaluations */
  public $referenceEvaluations;

  /** @inject @var SubmissionHelper */
  public $submissionHelper;

  /**
   * @POST
   * @Param(type="post", name="note")
   * @Param(type="post", name="files")
   * @Param(type="post", name="hwGroup")
   */
  public function actionExercise($id) {
    // @todo check that this user can access this information
    $exercise = $this->findExerciseOrThrow($id);
    $this->sendSuccessResponse($exercise->referenceSolutions->toArray());
  }

  public function actionCreateReferenceSolution() {
    $exercise = $this->exercises->findOrThrow($id);
    $user = $this->users->findOrThrow("me");

    // @todo validate user's access

    $req = $this->getHttpRequest();
    $files = $this->files->findAllById($req->getPost("files"));
    $note = $req->getPost("note");
    $solution = new ReferenceExerciseSolution($exercise, $user, $note, $files);
    $this->referenceSolutions->persist($solution);

    // evaluate the solution right now
    $this->actionSubmit($solution->getId());
  }

  /**
   * @POST
   */
  public function actionEvaluate(string $exerciseId, string $id) {
    $referenceSolution = $this->referenceSolutions->findOrThrow($id);
    $user = $this->users->findOrThrow("me");

    if ($referenceSolution->getExercise()->getId() !== $exerciseId) {
      // @todo throw some exception to report inconsistence
    }

    // @todo validate that user can do this action

    // create the entity and generate the ID
    $hwGroup = $this->getHttpRequest()->getPost("hwGroup");
    $evaluation = new ReferenceSolutionEvaluation($referenceSolution, $hwGroup);
    $this->referenceEvaluations->persist($evaluation);

    // configure the job and start evaluation
    $jobConfig = JobConfig\Loader::getJobConfig($referenceSolution->getExercise()->getJobConfigFilePath());
    $jobConfig->setJobId(ReferenceSolutionEvaluation::JOB_TYPE, $evaluation->getId());
    $files = $referenceSolution->getFiles()->toArray();
    $resultsUrl = $this->submissionHelper->initiateEvaluation($jobConfig, $files, $hwGroup);

    if($resultsUrl !== NULL) {
      $evaluation->setResultsUrl($resultsUrl);
      $this->referenceEvaluations->flush();
      $this->sendSuccessResponse([
        "evaluation" => $evaluation,
        "webSocketChannel" => [
          "id" => $jobConfig->getJobId(),
          "monitorUrl" => $this->getMonitorUrl(),
          "expectedTasksCount" => $jobConfig->getTasksCount()
        ],
      ]);
    } else {
      throw new SubmissionFailedException;
    }
  }

  private function getMonitorUrl() {
    return Arrays::get($this->getContext()->parameters, ["monitor", "address"], NULL);
  }

}
