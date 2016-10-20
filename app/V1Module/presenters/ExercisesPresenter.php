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
   * @GET
   * @UserIsAllowed(exercises="view-detail")
   */
  public function actionDetail(string $id) {
    $exercise = $this->findExerciseOrThrow($id);
    $this->sendSuccessResponse($exercise);
  }

  /**
   * @POST
   * @UserIsAllowed(exercises="update")
   */
  public function actionUpdateDetail(string $id) {
    $req = $this->getHttpRequest();

    // TODO

    $this->sendSuccessResponse();
  }

  /**
   * @POST
   * @UserIsAllowed(exercises="create")
   */
  public function actionCreate() {
    $req = $this->getHttpRequest();

    // TODO

    $this->sendSuccessResponse();
  }

  /**
   * @POST
   * @UserIsAllowed(exercises="fork")
   */
  public function actionForkFrom(string $id) {
    $req = $this->getHttpRequest();

    // TODO

    $this->sendSuccessResponse();
  }

}
