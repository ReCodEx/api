<?php

namespace App\V1Module\Presenters;

use App\Helpers\EvaluationLoader;
use App\Model\Repository\Submissions;
use App\Model\Repository\SolutionEvaluations;
use App\Exceptions\NotFoundException;

/**
 * Endpoints for manipulation of solution submissions
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
    $submission = $this->submissions->findOrThrow($id);
    if (!$submission->hasEvaluation()) { // the evaluation must be loaded first
      $evaluation = $this->evaluationLoader->load($submission);
      if ($evaluation !== NULL) {
        $this->evaluations->persist($evaluation);
        $this->submissions->persist($submission);
      } else {
        // the evaluation is probably not ready yet
        // - display partial information about the submission, do not throw an error
      }
    }

    $this->sendSuccessResponse($submission);
  }

}
