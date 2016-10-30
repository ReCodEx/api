<?php

namespace App\V1Module\Presenters;

use App\Helpers\EvaluationLoader;

use App\Model\Entity\Submission;
use App\Model\Repository\Submissions;
use App\Model\Repository\SolutionEvaluations;
use App\Exceptions\NotFoundException;
use App\Exceptions\SubmissionEvaluationFailedException;

/**
 * @LoggedIn
 */
class SubmissionsPresenter extends BasePresenter {

  /**
   * @var Submissions
   * @inject
   */
  public $submissions;

  /**
   * @var SolutionEvaluations
   * @inject
   */
  public $evaluations;

  /**
   * @var EvaluationLoader
   * @inject
   */
  public $evaluationLoader;

  /**
   * @param string $id
   * @return Submission
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
   * Get a list of all submissions, ever
   * @GET
   * @UserIsAllowed(submissions="view-all")
   */
  public function actionDefault() {
    $submissions = $this->submissions->findAll();
    $this->sendSuccessResponse($submissions);
  }

  /**
   * Get information about the evaluation of a submission
   * @GET
   * @UserIsAllowed(submissions="view-evaluation")
   * @param string $id
   * @throws NotFoundException
   */
  public function actionEvaluation(string $id) {
    $submission = $this->findSubmissionOrThrow($id);
    if (!$submission->hasEvaluation()) { // the evaluation must be loaded first
      try {
        $evaluation = $this->evaluationLoader->load($submission);
        $this->evaluations->persist($evaluation);
        $this->submissions->persist($submission);
      } catch (SubmissionEvaluationFailedException $e) {
        // the evaluation is probably not ready yet
        // - display partial information about the submission, do not throw an error
      }
    }

    $this->sendSuccessResponse($submission);
  }

}
