<?php

namespace App\V1Module\Presenters;

use App\Exception\NotFoundException;
use App\Model\Repository\Exercises;

/**
 * @LoggedIn
 */
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
      throw new NotFoundException;
    }

    return $exercise;
  }

  /**
   * @GET
   */
  public function actionDefault() {
    $exercises = $this->exercises->findAll();
    $this->sendSuccessResponse($exercises);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $exercise = $this->findExerciseOrThrow($id);
    $this->sendSuccessResponse($exercise);
  }

}
