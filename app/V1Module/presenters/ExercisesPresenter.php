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
use App\Model\Entity\AdditionalExerciseFile;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Helpers\JobConfig;
use App\Helpers\UploadedJobConfigStorage;
use App\Helpers\ExerciseFileStorage;
use App\Model\Entity\RuntimeConfig;
use App\Model\Repository\RuntimeConfigs;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\ReferenceSolutionEvaluations;
use App\Model\Repository\HardwareGroups;
use App\Model\Entity\LocalizedText;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\ExerciseFiles;
use Exception;
use Nette\Utils\Arrays;

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
   * @var JobConfig\Storage
   * @inject
   */
  public $jobConfigs;

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
   * @var RuntimeConfigs
   * @inject
   */
  public $runtimeConfigurations;

  /**
   * @var ReferenceSolutionEvaluations
   * @inject
   */
  public $referenceSolutionEvaluations;

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
   * @Param(type="post", name="localizedTexts", validation="array", description="A description of the exercise")
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

    // retrieve localizations and prepare some temp variables
    $localizedTexts = $req->getPost("localizedTexts");
    $localizations = [];

    // localized texts cannot be empty
    if (count($localizedTexts) == 0) {
      throw new InvalidArgumentException("No entry for localized texts given.");
    }

    // go through given localizations and construct database entities
    foreach ($localizedTexts as $localization) {
      $lang = $localization["locale"];

      if (array_key_exists($lang, $localizations)) {
        throw new InvalidArgumentException("Duplicate entry for language $lang");
      }

      // create all new localized texts
      $localized = new LocalizedText(
        $localization["text"],
        $lang,
        $exercise->getLocalizedTextByLocale($lang)
      );

      $localizations[$lang] = $localized;
    }

    // make changes to database
    $this->exercises->replaceLocalizedTexts($exercise, array_values($localizations), FALSE);
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
   * @Param(type="post", name="runtimeConfigs", validation="array", description="Runtime configurations for the exercise")
   */
  public function actionUpdateRuntimeConfigs(string $id) {
    $req = $this->getRequest();
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user) && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not author of this exercise, thus you cannot update it.");
    }

    // retrieve configuration and prepare some temp variables
    $runtimeConfigs = $req->getPost("runtimeConfigs");
    $configs = [];

    // configurations cannot be empty
    if (count($runtimeConfigs) == 0) {
      throw new InvalidArgumentException("No entry for runtime configurations given.");
    }

    // go through given configurations and construct database entities
    foreach ($runtimeConfigs as $runtimeConfig) {
      $environmentId = $runtimeConfig["runtimeEnvironmentId"];
      $environment = $this->runtimeEnvironments->findOrThrow($environmentId);

      if (array_key_exists($environmentId, $configs)) {
        throw new InvalidArgumentException("Duplicate entry for configuration $environmentId");
      }

      // store job configuration into file
      $jobConfigPath = $this->uploadedJobConfigStorage->storeContent($runtimeConfig["jobConfig"], $user);
      if ($jobConfigPath === NULL) {
        throw new JobConfigStorageException;
      }

      // create all new runtime configuration
      $config = new RuntimeConfig(
        $runtimeConfig["name"],
        $environment,
        $jobConfigPath,
        $exercise->getRuntimeConfigByEnvironment($environment)
      );

      $configs[$environmentId] = $config;
    }

    // make changes to database
    $this->exercises->replaceRuntimeConfigs($exercise, $configs, FALSE);
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

    $this->sendSuccessResponse($exercise->getSupplementaryEvaluationFiles()->getValues());
  }

  /**
   * Associate additional exercise files with an exercise
   * @POST
   * @UserIsAllowed(exercises="update")
   * @Param(type="post", name="files", description="Identifiers of additional files")
   * @param string $id identification of exercise
   * @throws BadRequestException
   * @throws CannotReceiveUploadedFileException
   * @throws ForbiddenRequestException
   */
  public function actionUploadAdditionalFiles(string $id) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user) && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not author of this exercise, thus you cannot upload files for it.");
    }

    $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
    $additionalFiles = [];

    foreach ($files as $file) {
      if (!($file instanceof UploadedFile)) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
      }

      $exerciseFile = AdditionalExerciseFile::fromUploadedFile($file, $exercise);
      $this->uploadedFiles->persist($exerciseFile, FALSE);
      $this->uploadedFiles->remove($file, FALSE);
    }

    $this->uploadedFiles->flush();
    $this->sendSuccessResponse($additionalFiles);
  }

  /**
   * Get a list of all additional files for an exercise
   * @GET
   * @UserIsAllowed(exercises="update")
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetAdditionalFiles(string $id) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->isAuthor($user) && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not author of this exercise, thus you cannot view supplementary files for it.");
    }

    $this->sendSuccessResponse($exercise->getAdditionalFiles()->getValues());
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
    $this->sendSuccessResponse($exercise);
  }

  /**
   * Delete an exercise
   * @DELETE
   * @UserIsAllowed(exercises="remove")
   * @param string $id
   * @throws ForbiddenRequestException
   */
  public function actionRemove(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    $this->exercises->remove($exercise);
    $this->sendSuccessResponse("OK");
  }

  /**
   * Fork exercise from given one into the completely new one.
   * @POST
   * @UserIsAllowed(exercises="create")
   */
  public function actionForkFrom(string $id) {
    $user = $this->getCurrentUser();
    $forkFrom = $this->exercises->findOrThrow($id);

    if (!$forkFrom->canAccessDetail($user)) {
      throw new ForbiddenRequestException("Exercise cannot be forked by you");
    }

    $exercise = Exercise::forkFrom($forkFrom, $user);
    $this->exercises->persist($exercise);
    $this->sendSuccessResponse($exercise);
  }

  /**
   * Get a description of resource limits for an exercise
   * @GET
   * @UserIsAllowed(exercises="view-limits")
   * @param string $id Identifier of the exercise
   */
  public function actionGetLimits(string $id) {
    $exercise = $this->exercises->findOrThrow($id);

    // get job config and its test cases
    $environments = $exercise->getRuntimeConfigs()->map(
      function ($environment) use ($exercise) {
        $jobConfig = $this->jobConfigs->getJobConfig($environment->getJobConfigFilePath());
        $referenceEvaluations = [];
        foreach ($jobConfig->getHardwareGroups() as $hwGroup) {
          $referenceEvaluations[$hwGroup] = $this->referenceSolutionEvaluations->find(
            $exercise,
            $environment->getRuntimeEnvironment(),
            $hwGroup
          );
        }

        return [
          "environment" => $environment,
          "hardwareGroups" => $jobConfig->getHardwareGroups(),
          "limits" => $jobConfig->getLimits(),
          "referenceSolutionsEvaluations" => $referenceEvaluations
        ];
      }
    );

    $this->sendSuccessResponse([ "environments" => $environments->getValues() ]);
  }

  /**
   * Set resource limits for an exercise
   * @POST
   * @UserIsAllowed(exercises="set-limits")
   * @Param(type="post", name="environments", description="A list of resource limits for the environments and hardware groups", validation="array")
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionSetLimits(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    $exerciseRuntimeConfigsIds = $exercise->getRuntimeConfigsIds();

    $req = $this->getRequest();
    $environments = $req->getPost("environments");

    if (count($environments) === 0) {
      throw new NotFoundException("No environment specified");
    }

    foreach ($environments as $environment) {
      $runtimeId = Arrays::get($environment, ["environment", "id"], NULL);
      $runtimeConfig = $this->runtimeConfigurations->findOrThrow($runtimeId);
      if (!in_array($runtimeId, $exerciseRuntimeConfigsIds)) {
        throw new ForbiddenRequestException("Cannot configure solution runtime configuration $runtimeId for exercise $id");
      }

      // open the job config and update the limits for all hardware groups
      $path = $runtimeConfig->getJobConfigFilePath();
      $jobConfig = $this->jobConfigs->getJobConfig($path);

      // get through all defined limits indexed by hwgroup
      $limits = Arrays::get($environment, ["limits"], []);
      foreach ($limits as $hwGroupLimits) {
        if (!isset($hwGroupLimits["hardwareGroup"])) {
          throw new InvalidArgumentException("environments[][limits][][hardwareGroup]");
        }

        $hardwareGroup = $hwGroupLimits["hardwareGroup"];
        $tests = Arrays::get($hwGroupLimits, ["tests"], []);
        $newLimits = array_reduce(array_values($tests), "array_merge", []);
        $jobConfig->setLimits($hardwareGroup, $newLimits);
      }

      // save the new & archive the old config
      $this->jobConfigs->saveJobConfig($jobConfig, $path);
    }

    // the same output as get limits
    $this->forward("getLimits", $id);
  }
}
