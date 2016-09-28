<?php

namespace App\V1Module\Presenters;

use App\Helpers\EvaluationLoader;

use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionEvaluation;
use App\Model\Entity\TestResult;
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

  /** @inject @var EvaluationLoader */
  public $evaluationLoader;

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
   * @UserIsAllowed(submissions="view-all")
   */
  public function actionDefault() {
    $submissions = $this->submissions->findAll();
    $this->sendSuccessResponse($submissions);
  }

  /**
   * @GET
   * @UserIsAllowed(submissions="view-evaluation")
   * @param string $id
   * @throws NotFoundException
   */
  public function actionEvaluation(string $id) {
    $submission = $this->findSubmissionOrThrow($id);
    $evaluation = $submission->getEvaluation();
    if (!$evaluation) { // the evaluation must be loaded first
      try {
        $evaluation = $this->evaluationLoader->load($submission);
      } catch (SubmissionEvaluationFailedException $e) {
        // the evaluation is probably not ready yet
        throw new NotFoundException("Evaluation is not available yet.");
      }

      $this->evaluations->persist($evaluation);
      $this->submissions->persist($submission);
    }

    $this->sendSuccessResponse($submission);
  }

}
