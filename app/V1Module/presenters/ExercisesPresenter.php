<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\JobConfigStorageException;
use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\NotFoundException;
use App\Helpers\UploadedFileStorage;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Helpers\UploadedJobConfigStorage;
use App\Helpers\ExerciseFileStorage;
use App\Model\Entity\SolutionRuntimeConfig;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\HardwareGroups;
use App\Model\Entity\LocalizedAssignment;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\ExerciseFiles;
use Exception;

/**
 * Endpoints for exercise manipulation
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
   * @var RuntimeEnvironments
   * @inject
   */
  public $runtimeEnvironments;

  /**
   * @var HardwareGroups
   * @inject
   */
  public $hardwareGroups;

  /**
   * @var UploadedFiles
   * @inject
   */
  public $uploadedFiles;

  /**
   * @var ExerciseFiles
   * @inject
   */
  public $supplementaryFiles;

  /**
   * @var ExerciseFileStorage
   * @inject
   */
  public $supplementaryFileStorage;

  /**
   * @var UploadedFileStorage
   * @inject
   */
  public $uploadedFileStorage;

  /**
   * Get a list of exercises with an optional filter
   * @GET
   * @UserIsAllowed(exercises="view-all")
   * @param string $search text which will be searched in exercises names
   */
  public function actionDefault(string $search = NULL) {
    $user = $this->getCurrentUser();
    $exercises = $this->exercises->searchByName($search, $user);
    $this->sendSuccessResponse($exercises);
  }

  /**
   * Get details of an exercise
   * @GET
   * @UserIsAllowed(exercises="view-detail")
   * @param string $id identification of exercise
   */
  public function actionDetail(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->canAccessDetail($this->getCurrentUser())) {
      throw new NotFoundException;
    }

    $this->sendSuccessResponse($exercise);
  }

  /**
   * Update detail of an exercise
   * @POST
   * @UserIsAllowed(exercises="update")
   * @param string $id identification of exercise
   * @Param(type="post", name="name", description="Name of exercise")
   * @Param(type="post", name="version", description="Version of the edited exercise")
   * @Param(type="post", name="description", description="Some brief description of this exercise for supervisors")
   * @Param(type="post", name="difficulty", description="Difficulty of an exercise, should be one of 'easy', 'medium' or 'hard'")
   * @Param(type="post", name="localizedAssignments", description="A description of the exercise")
   * @Param(type="post", name="isPublic", description="Exercise can be public or private", validation="bool", required=FALSE)
   */
  public function actionUpdateDetail(string $id) {
    $req = $this->getRequest();
    $name = $req->getPost("name");
    $difficulty = $req->getPost("difficulty");
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
    $description = $req->getPost("description");

    // check if user can modify requested exercise
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user) && $user->getRole()->hasLimitedRights()) {
      throw new BadRequestException("You are not author of this exercise, thus you cannot update it.");
    }

    $version = intval($req->getPost("version"));
    if ($version !== $exercise->getVersion()) {
      throw new BadRequestException("The exercise was edited in the meantime and the version has changed. Current version is {$exercise->getVersion()}."); // @todo better exception
    }

    // make changes to newly created excercise
    $exercise->setName($name);
    $exercise->setDifficulty($difficulty);
    $exercise->setIsPublic($isPublic);
    $exercise->setUpdatedAt(new \DateTime);
    $exercise->incrementVersion();
    $exercise->setDescription($description);

    // add new and update old localizations
    $postLocalized = $req->getPost("localizedAssignments");
    $localizedAssignments = $postLocalized && is_array($postLocalized) ? $postLocalized : array();
    $localizations = [];

    foreach ($localizedAssignments as $localization) {
      $lang = $localization["locale"];

      if (array_key_exists($lang, $localizations)) {
        throw new InvalidArgumentException("Duplicate entry for language $lang");
      }

      // create all new localized assignments
      $localized = new LocalizedAssignment(
        $localization["name"],
        $localization["description"],
        $lang,
        $exercise->getLocalizedAssignmentByLocale($lang)
      );

      $localizations[$lang] = $localized;
    }

    // make changes to database
    $this->exercises->replaceLocalizedAssignments($exercise, array_values($localizations), FALSE);
    $this->exercises->flush();
    $this->sendSuccessResponse($exercise);
  }

  /**
   * Check if the version of the exercise is up-to-date.
   * @POST
   * @UserIsAllowed(exercises="update")
   * @Param(type="post", name="version", validation="numericint", description="Version of the exercise.")
   * @param string $id Identifier of the exercise
   */
  public function actionValidate($id) {
    $exercise = $this->exercises->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$exercise->isAuthor($user)
        && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot access this assignment.");
    }

    $req = $this->getHttpRequest();
    $version = intval($req->getPost("version"));

    $this->sendSuccessResponse([
      "versionIsUpToDate" => $exercise->getVersion() === $version
    ]);
  }

  /**
   * Change runtime configuration of exercise.
   * Configurations can be added or deleted here, based on what is provided in arguments.
   * @POST
   * @UserIsAllowed(exercises="update")
   * @param string $id identification of exercise
   * @Param(type="post", name="runtimeConfigs", description="Runtime configurations for the exercise")
   */
  public function actionUpdateRuntimeConfigs(string $id) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user) && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not author of this exercise, thus you cannot update it.");
    }

    // add new and update old runtime configs
    $req = $this->getRequest();
    $runtimeConfigs = $req->getPost("runtimeConfigs");
    $usedConfigs = [];
    foreach ($runtimeConfigs as $runtimeConfig) {
      $name = $runtimeConfig["name"];
      $environmentId = $runtimeConfig["runtimeEnvironmentId"];
      $jobConfig = $runtimeConfig["jobConfig"];
      $environment = $this->runtimeEnvironments->get($environmentId);

      // store job configuration into file
      $jobConfigPath = $this->uploadedJobConfigStorage->storeContent($jobConfig, $user);
      if ($jobConfigPath === NULL) {
        throw new JobConfigStorageException;
      }

      // create all new runtime configs
      $originalConfig = $exercise->getRuntimeConfigByEnvironment($environment);
      $config = new SolutionRuntimeConfig($name, $environment, $jobConfigPath);
      if ($originalConfig) {
        $config->setSolutionRuntimeConfig($originalConfig);
        $exercise->removeSolutionRuntimeConfig($originalConfig);
      }
      $exercise->addSolutionRuntimeConfig($config);
      $usedConfigs[] = $environmentId;
    }

    // remove unused configs
    foreach ($exercise->getSolutionRuntimeConfigs() as $runtimeConfig) {
      if (!in_array($runtimeConfig->getRuntimeEnvironment()->getId(), $usedConfigs)) {
        $exercise->removeSolutionRuntimeConfig($runtimeConfig);
      }
    }

    // make changes to database
    $this->exercises->persist($exercise);
    $this->exercises->flush();
    $this->sendSuccessResponse($exercise);
  }

  /**
   * Associate supplementary files with an exercise and upload them to remote file server
   * @POST
   * @UserIsAllowed(exercises="update")
   * @Param(type="post", name="files", description="Identifiers of supplementary files")
   * @param string $id identification of exercise
   * @throws BadRequestException
   * @throws CannotReceiveUploadedFileException
   * @throws ForbiddenRequestException
   */
  public function actionUploadSupplementaryFiles(string $id) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user) && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not author of this exercise, thus you cannot upload files for it.");
    }

    $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
    $supplementaryFiles = [];
    $deletedFiles = [];

    foreach ($files as $file) {
      if (!($file instanceof UploadedFile)) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
      }

      $supplementaryFiles[] = $exerciseFile = $this->supplementaryFileStorage->store($file, $exercise);
      $this->uploadedFiles->persist($exerciseFile, FALSE);
      $this->uploadedFiles->remove($file, FALSE);
      $deletedFiles[] = $file;
    }

    $this->uploadedFiles->flush();

    /** @var UploadedFile $file */
    foreach ($deletedFiles as $file) {
      try {
        $this->uploadedFileStorage->delete($file);
      } catch (Exception $e) {} // TODO not worth aborting the request - log it?
    }

    $this->sendSuccessResponse($supplementaryFiles);
  }

  /**
   * Get list of all supplementary files for an exercise
   * @GET
   * @UserIsAllowed(exercises="update")
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetSupplementaryFiles(string $id) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user) && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not author of this exercise, thus you cannot view supplementary files for it.");
    }

    $this->sendSuccessResponse($exercise->getSupplementaryFiles()->getValues());
  }

  /**
   * Create exercise with all default values.
   * Exercise detail can be then changed in appropriate endpoint.
   * @POST
   * @UserIsAllowed(exercises="create")
   */
  public function actionCreate() {
    $user = $this->getCurrentUser();

    $exercise = Exercise::create($user);
    $exercise->setName("Exercise by " . $user->getName());
    $this->exercises->persist($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($exercise);
  }
}
