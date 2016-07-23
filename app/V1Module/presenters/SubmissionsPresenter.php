<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Submissions;

class SubmissionsPresenter extends BasePresenter {

  /** @var Submissions */
  private $submissions;

  /**
   * @param Submissions $submissions  Submissions repository
   */
  public function __construct(Submissions $submissions) {
    $this->submissions = $submissions;
  }

  protected function findSubmissionOrThrow(string $id) {
    $submission = $this->submissions->get($id);
    if (!$submission) {
      // @todo report a 404 error
      throw new Exception;
    }

    return $submission;
  }

  public function actionGetAll() {
    $submissions = $this->submissions->findAll();
    $this->sendJson($submissions);
  }

  public function actionDetail(string $id) {
    $submission = $this->findSubmissionOrThrow($id);
    $this->sendJson($submission);
  }

  // @todo: evaluation

}
