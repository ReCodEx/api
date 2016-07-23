<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Exercises;

class ExercisesPresenter extends BasePresenter {

  /** @var Exercises */
  private $exercises;

  /**
   * @param Exercises $exercises  Exercises repository
   */
  public function __construct(Exercises $exercises) {
    $this->exercises = $exercises;
  }

  protected function findExerciseOrThrow(string $id) {
    $exercise = $this->exercises->get($id);
    if (!$exercise) {
      // @todo report a 404 error
      throw new Exception;
    }

    return $exercise;
  }

  public function actionGetAll() {
    $exercises = $this->exercises->findAll();
    $this->sendJson($exercises);
  }

  public function actionDetail(string $id) {
    $exercise = $this->findExerciseOrThrow($id);
    $this->sendJson($exercise);
  }

}
