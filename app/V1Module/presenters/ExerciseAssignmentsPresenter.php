<?php

namespace App\V1Module\Presenters;

use App\Exception\NotFoundException;
use App\Model\Repository\ExerciseAssignments;
use App\Model\Repository\Submissions;

class ExerciseAssignmentsPresenter extends BasePresenter {

  /** @var ExerciseAssignments */
  private $assignments;

  /** @var Submissions */
  private $submissions;

  /**
   * @param ExerciseAssignments $assignments  ExerciseAssignments repository
   * @param Submissions         $submissions  Submissions repository
   */
  public function __construct(ExerciseAssignments $assignments, Submissions $submissions) {
    $this->assignments = $assignments;
    $this->submissions = $submissions;
  }

  protected function findExerciseOrThrow(string $id) {
    $assignment = $this->assignments->get($id);
    if (!$assignment) {
      throw new NotFoundException;
    }

    return $assignment;
  }

  /**
   * @GET
   */
  public function actionDefault() {
    $assignments = $this->assignments->findAll();
    $this->sendJson($assignments);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $assignment = $this->findExerciseOrThrow($id);
    $this->sendJson($assignment);
  }

  /**
   * @GET
   */
  public function actionSubmissions(string $id, string $userId) {
    $assignment = $this->findExerciseOrThrow($id);
    $submissions = $this->submissions->findSubmissions($assignment, $userId);
    $this->sendJson($submissions);
  }

}
