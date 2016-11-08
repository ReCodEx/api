<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\JobConfigStorageException;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Helpers\UploadedJobConfigStorage;
use App\Model\Entity\SolutionRuntimeConfig;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\HardwareGroups;
use App\Model\Entity\LocalizedAssignment;

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
   * Get a list of exercises with an optional filter
   * @GET
   * @UserIsAllowed(exercises="view-all")
   */
  public function actionDefault(string $search = NULL) {
    $exercises = $search === NULL ? $this->exercises->findAll() : $this->exercises->searchByName($search);

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
   * @Param(type="post", name="difficulty")
   * @Param(type="post", name="localizedAssignments", description="A description of the assignment")
   */
  public function actionUpdateDetail(string $id) {
    $req = $this->getRequest();
    $name = $req->getPost("name");
    $difficulty = $req->getPost("difficulty");

    // check if user can modify requested exercise
    $user = $this->users->findCurrentUserOrThrow();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user)) {
      throw new BadRequestException("You are not author of this exercise, thus you cannot update it.");
    }

    // make changes to newly created excercise
    $forkedExercise = Exercise::forkFrom($exercise, $user);
    $forkedExercise->setName($name);
    $forkedExercise->setDifficulty($difficulty);

    // add new and update old localizations
    $localizedAssignments = $req->getPost("localizedAssignments");
    foreach ($localizedAssignments as $localization) {
      $lang = $localization["locale"];
      $description = $localization["description"];
      $localizationName = $localization["name"];

      // update or create the localization
      $localized = $forkedExercise->getLocalizedAssignmentByLocale($lang);
      if (!$localized) {
        $localized = new LocalizedAssignment($localizationName, $description, $lang);
        $forkedExercise->addLocalizedAssignment($localized);
      } else {
        $forkedLocalized = new LocalizedAssignment($localized->getName(), $localized->getDescription(), $localized->getLocale());
        $forkedExercise->addLocalizedAssignment($forkedLocalized);
      }
    }

    // make changes to database
    $this->exercises->persist($forkedExercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($forkedExercise);
  }

  /**
   * @POST
   * @UserIsAllowed(exercises="update")
   * @Param(type="post", name="runtimeConfigs", description="A description of the assignment")
   */
  public function actionUpdateRuntimeConfigs(string $id) {
    $user = $this->users->findCurrentUserOrThrow();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user)) {
      throw new BadRequestException("You are not author of this exercise, thus you cannot update it.");
    }

    // add new and update old runtime configs
    $req = $this->getRequest();
    $runtimeConfigs = $req->getPost("runtimeConfigs");
    $currentConfigs = [];
    foreach ($runtimeConfigs as $runtimeConfig) {
      $customName = $runtimeConfig["customName"];
      $environmentId = $runtimeConfig["environmentId"];
      $jobConfig = $runtimeConfig["jobConfig"];
      $hwGroupId = $runtimeConfig["hardwareGroupId"];
      $environment = $this->runtimeEnvironments->get($environmentId);
      $hwGroup = $this->hardwareGroups->get($hwGroupId);

      // store job configuration into file
      $jobConfigPath = $this->uploadedJobConfigStorage->storeContent($jobConfig, $user);
      if ($jobConfigPath === NULL) {
        throw new JobConfigStorageException;
      }

      // update or create the runtime config
      $config = $exercise->getRuntimeConfigByEnvironment($environmentId);
      if (!$config) {
        $config = new SolutionRuntimeConfig($customName, $environment, $jobConfigPath, $hwGroup);
        $exercise->addSolutionRuntimeConfigs($config);
      } else {
        $config->setCustomName($customName);
        $config->setJobConfigFilePath($jobConfigPath);
        $config->setHardwareGroup($hwGroup);
      }
      $currentConfigs[] = $environmentId;
    }

    // remove unused configs
    foreach ($exercise->getSolutionRuntimeConfigs() as $runtimeConfig) {
      if (!in_array($runtimeConfig->getRuntimeEnvironment()->getId(), $currentConfigs)) {
        $exercise->removeSolutionRuntimeConfigs($runtimeConfig);
      }
    }

    // make changes to database
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
