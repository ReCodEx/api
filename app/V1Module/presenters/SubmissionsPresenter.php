<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionEvaluation;
use App\Model\Entity\TestResult;
use App\Helpers\FileServerProxy;
use App\Helpers\EvaluationResults;
use App\Helpers\JobConfig;
use App\Helpers\SimpleScoreCalculator;
use App\Model\Repository\Submissions;
use App\Model\Repository\SubmissionEvaluations;
use App\Model\Repository\ExerciseAssignments;
use App\Model\Repository\UploadedFiles;

use App\Exceptions\NotFoundException;
use App\Exceptions\BadRequestException;
use App\Exceptions\SubmissionEvaluationFailedException;

/**
 * @LoggedIn
 */
class SubmissionsPresenter extends BasePresenter {

  /** @inject @var Submissions */
  public $submissions;

  /** @inject @var SubmissionEvaluations */
  public $evaluations;

  /** @inject @var FileServerProxy */
  public $fileServer;

  /**
   * @param string $id
   * @return mixed
   * @throws NotFoundException
   */
  protected function findSubmissionOrThrow(string $id) {
    $submission = $this->submissions->get($id);
    if (!$submission) {
      throw new NotFoundException("Submission $id");
    }

    return $submission;
  }

  /**
   * @GET
   */
  public function actionDefault() {
    $submissions = $this->submissions->findAll();
    $this->sendSuccessResponse($submissions);
  }

  /**
   * @GET
   * @param string $id
   * @throws NotFoundException
   */
  public function actionEvaluation(string $id) {
    $submission = $this->findSubmissionOrThrow($id);
    $evaluation = $submission->getEvaluation();
    if (!$evaluation) { // the evaluation must be loaded first
      // try {
        $evaluation = $this->loadEvaluation($submission);
      // } catch (SubmissionEvaluationFailedException $e) {
        // the evaluation is probably not ready yet
        // throw new NotFoundException("Evaluation is not available yet.");
      // }

      $this->evaluations->persist($evaluation);
      $this->submissions->persist($submission);
    }

    $this->sendSuccessResponse($submission);
  }

  private function loadEvaluation(Submission $submission) {
    $yml = $this->fileServer->downloadResults($submission->resultsUrl);
    $jobConfig = JobConfig\Loader::getJobConfig($submission);
    $results = EvaluationResults\Loader::parseResults($yml, $jobConfig);
    $evaluation = new SubmissionEvaluation($submission, $results);

    // calcutate the total score
    if (!!$results) {
      $evaluation->setTestResults($results->getTestsResults($submission->getHardwareGroup()));
      $evaluation->updateScore(new SimpleScoreCalculator($submission->getExerciseAssignment()->getScoreConfig()));
    } else {
      $evaluation->setScore(0);
    }

    $evaluation->setPoints($evaluation->getScore() * $submission->getMaxPoints());
    return $evaluation;
  }

}
