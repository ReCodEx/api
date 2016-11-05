<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Helpers\UploadedJobConfigStorage;

/**
 * Endpoint for exercise manipulation
 * @LoggedIn
 */
class ExercisesPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   * @var UploadedJobConfigStorage
   * @inject
   */
  public $uploadedJobConfigStorage;

  /**
   * Get a list of exercises with an optional filter
   * @GET
   * @UserIsAllowed(exercises="view-all")
   */
  public function actionDefault(string $search = NULL) {
    $exercises = $search === NULL ? $this->exercises->findAll() : $this->exercises->searchByNameOrId($search);

    $this->sendSuccessResponse($exercises);
  }

  /**
   * Get details of an exercise
   * @GET
   * @UserIsAllowed(exercises="view-detail")
   */
  public function actionDetail(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    $this->sendSuccessResponse($exercise);
  }

  /**
   * @POST
   * @UserIsAllowed(exercises="update")
   * @Param(type="post", name="name")
   * @Param(type="post", name="description")
   * @Param(type="post", name="difficulty")
   */
  public function actionUpdateDetail(string $id) { // TODO: this has to be change to reflect localizedAssignment structures
    $req = $this->getRequest();
    $name = $req->getPost("name");
    $description = $req->getPost("description");
    $difficulty = $req->getPost("difficulty");

    // check if user can modify requested exercise
    $user = $this->users->findCurrentUserOrThrow();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user)) {
      throw new BadRequestException("You are not author of this exercise, thus you cannot update it.");
    }

    // save changes to database
    $exercise->update($name, $description, $difficulty);
    $this->exercises->persist($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($exercise);
  }

  /**
   * @POST
   * @UserIsAllowed(exercises="update")
   */
  public function actionUploadJobConfig(string $id) {
    $user = $this->users->findCurrentUserOrThrow();
    $exercise = $this->exercises->findOrThrow($id);
    if ($exercise->isAuthor($user)) {
      throw new BadRequestException("You are not author of this exercise, thus you cannot update it.");
    }

    // get file from request and check if there is only one file
    $files = $this->getHttpRequest()->getFiles();
    if (count($files) === 0) {
      throw new BadRequestException("No file was uploaded");
    } elseif (count($files) > 1) {
        throw new BadRequestException("Too many files were uploaded");
    }

    // store file on application filesystem
    $file = array_pop($files);
    $uploadedFile = $this->uploadedJobConfigStorage->store($file, $user);
    if ($uploadedFile === NULL) {
      throw new CannotReceiveUploadedFileException($file->getSanitizedName());
    }

    // make changes to exercise entity
    $exercise->setJobConfigFilePath($uploadedFile);
    $this->exercises->persist($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($exercise);
  }

  /**
   * @POST
   * @UserIsAllowed(exercises="create")
   */
  public function actionCreate() {
    $user = $this->users->findCurrentUserOrThrow();

    $exercise = Exercise::create($user);
    $this->exercises->persist($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($exercise);
  }

  /**
   * @POST
   * @UserIsAllowed(exercises="fork")
   */
  public function actionForkFrom(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    $forkedExercise = Exercise::forkFrom($exercise, $user);
    $this->exercises->persist($forkedExercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($forkedExercise);
  }

}
