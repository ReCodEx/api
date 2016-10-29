<?php

namespace App\V1Module\Presenters;

use App\Exceptions\NotFoundException;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;

/**
 * @LoggedIn
 */
class ExercisesPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   *
   * @param string $id
   * @return Exercise
   * @throws NotFoundException
   */
  protected function findExerciseOrThrow(string $id) {
    $exercise = $this->exercises->get($id);
    if (!$exercise) {
      throw new NotFoundException;
    }

    return $exercise;
  }

  /**
   * Get a list of exercises with an optional filter
   * @GET
   * @UserIsAllowed(exercises="view-all")
   */
  public function actionDefault(string $search = NULL) {
    $exercises = $search === NULL
      ? $this->exercises->findAll()
      : $this->exercises->searchByNameOrId($search);

    $this->sendSuccessResponse($exercises);
  }

  /**
   * Get details of an exercise
   * @GET
   * @UserIsAllowed(exercises="view-detail")
   */
  public function actionDetail(string $id) {
    $exercise = $this->findExerciseOrThrow($id);
    $this->sendSuccessResponse($exercise);
  }

}
