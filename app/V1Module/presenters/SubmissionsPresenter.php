<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionEvaluation;
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

  /** @var Submissions */
  private $submissions;

  /** @var SubmissionEvaluations */
  private $evaluations;

  /**
   * @param Submissions $submissions  Submissions repository
   * @param SubmissionEvaluations $evaluations  Submission evaluations repository
   */
  public function __construct(Submissions $submissions, SubmissionEvaluations $evaluations) {
    $this->submissions = $submissions;
    $this->evaluations = $evaluations;
  }

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
        $evaluation = SubmissionEvaluation::loadEvaluation($submission);
        $this->evaluations->persist($evaluation);
        $this->submissions->persist($submission); // save the new binding
      } catch (SubmissionEvaluationFailedException $e) {
        // the evaluation is probably not ready yet
        throw new NotFoundException('Evaluation is not available yet.');
      }
    }

    $this->sendSuccessResponse([
      'submission' => $submission,
      'evaluation' => $evaluation
    ]);
  }

}
