<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionEvaluation;
use App\Model\Helpers\FileServerProxy;
use App\Model\Repository\Submissions;
use App\Model\Repository\SubmissionEvaluations;
use App\Model\Repository\ExerciseAssignments;
use App\Model\Repository\UploadedFiles;

use App\Exception\NotFoundException;
use App\Exception\BadRequestException;
use App\Exception\SubmissionEvaluationFailedException;

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
      try {
        $result = $this->fileServer->downloadResults($submission->resultsUrl);
        $evaluation = SubmissionEvaluation::loadEvaluation($submission, $result);
        $this->evaluations->persist($evaluation);
        $this->submissions->persist($submission); // save the new binding
      } catch (SubmissionEvaluationFailedException $e) {
        // the evaluation is probably not ready yet
        throw new NotFoundException("Evaluation is not available yet.");
      }
    }

    $this->sendSuccessResponse($submission);
  }

}
