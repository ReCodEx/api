<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\JobConfigStorageException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Transformer;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseLimits;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Repository\Exercises;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\ReferenceSolutionEvaluations;
use App\Model\Repository\RuntimeEnvironments;
use App\Security\ACL\IExercisePermissions;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Endpoints for exercise configuration manipulation
 * @LoggedIn
 */

class ExercisesConfigPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   * @var Loader
   * @inject
   */
  public $exerciseConfigLoader;

  /**
   * @var Transformer
   * @inject
   */
  public $exerciseConfigTransformer;

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
   * @var ReferenceSolutionEvaluations
   * @inject
   */
  public $referenceSolutionEvaluations;

  /**
   * @var IExercisePermissions
   * @inject
   */
  public $exerciseAcl;

  /**
   * Get runtime configurations for exercise.
   * @GET
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionGetEnvironmentConfigs(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
    }

    $configs = array();
    foreach ($exercise->getExerciseEnvironmentConfigs() as $runtimeConfig) {
      $runtimeConfigArr = array();
      $runtimeConfigArr["runtimeEnvironmentId"] = $runtimeConfig->getRuntimeEnvironment()->getId();
      $runtimeConfigArr["variablesTable"] = $runtimeConfig->getParsedVariablesTable();
      $configs[] = $runtimeConfigArr;
    }

    $this->sendSuccessResponse($configs);
  }

  /**
   * Change runtime configuration of exercise.
   * Configurations can be added or deleted here, based on what is provided in arguments.
   * @POST
   * @param string $id identification of exercise
   * @Param(type="post", name="runtimeConfigs", validation="array", description="Runtime configurations for the exercise")
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws JobConfigStorageException
   */
  public function actionUpdateEnvironmentConfigs(string $id) {
    $req = $this->getRequest();
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot update this exercise.");
    }

    // retrieve configuration and prepare some temp variables
    $runtimeConfigs = $req->getPost("runtimeConfigs");
    $configs = [];

    // configurations cannot be empty
    if (count($runtimeConfigs) == 0) {
      throw new InvalidArgumentException("No entry for runtime configurations given.");
    }

    $runtimeEnvironments = new ArrayCollection;
    // go through given configurations and construct database entities
    foreach ($runtimeConfigs as $runtimeConfig) {
      $environmentId = $runtimeConfig["runtimeEnvironmentId"];
      $environment = $this->runtimeEnvironments->findOrThrow($environmentId);

      // add runtime environment into resulting environments
      $runtimeEnvironments->add($environment);

      // check for duplicate environments
      if (array_key_exists($environmentId, $configs)) {
        throw new InvalidArgumentException("Duplicate entry for configuration $environmentId");
      }

      // load variables table for this runtime configuration
      $variablesTable = $this->exerciseConfigLoader->loadVariablesTable($runtimeConfig["variablesTable"]);

      // create all new runtime configuration
      $config = new ExerciseEnvironmentConfig(
        $environment,
        (string) $variablesTable,
        $exercise->getExerciseEnvironmentConfigByEnvironment($environment)
      );

      $configs[$environmentId] = $config;
    }

    // replace all environments in exercise by the new ones
    $exercise->setRuntimeEnvironments($runtimeEnvironments);

    // make changes to database
    $this->exercises->replaceEnvironmentConfigs($exercise, $configs, FALSE);
    $this->exercises->flush();
    $this->sendSuccessResponse($exercise);
  }

  /**
   * Get a basic exercise high level configuration.
   * @GET
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionGetConfiguration(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
    }

    $exerciseConfig = $exercise->getExerciseConfig();
    if ($exerciseConfig === NULL) {
      throw new NotFoundException("Configuration for the exercise not exists");
    }

    $parsedConfig = $this->exerciseConfigLoader->loadExerciseConfig($exerciseConfig->getParsedConfig());

    // create configuration array which will be returned
    $config = $this->exerciseConfigTransformer->fromExerciseConfig($parsedConfig);
    $this->sendSuccessResponse($config);
  }

  /**
   * Set basic exercise configuration
   * @POST
   * @Param(type="post", name="config", description="A list of basic high level exercise configuration", validation="array")
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionSetConfiguration(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
    }

    $oldConfig = $exercise->getExerciseConfig();
    if ($oldConfig === NULL) {
      throw new NotFoundException("Configuration for the exercise not exists");
    }

    // get configuration from post request and transform it into internal structure
    $req = $this->getRequest();
    $config = $req->getPost("config");
    $exerciseConfig = $this->exerciseConfigTransformer->toExerciseConfig($config);

    // new config was provided, so construct new database entity
    $newConfig = new ExerciseConfig((string) $exerciseConfig, $oldConfig);

    // set new exercise configuration into exercise and flush changes
    $exercise->setExerciseConfig($newConfig);
    $this->exercises->flush();

    $config = $this->exerciseConfigTransformer->fromExerciseConfig($exerciseConfig);
    $this->sendSuccessResponse($config);
  }

  /**
   * Get a description of resource limits for an exercise
   * @GET
   * @param string $id Identifier of the exercise
   * @param string $runtimeEnvironmentId
   * @param string $hwGroupId
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionGetLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canViewLimits($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to get limits for this exercise.");
    }

    $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
    $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);

    $limits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
    if ($limits === NULL) {
      throw new NotFoundException("Limits for exercise cannot be found");
    }

    $this->sendSuccessResponse($limits->getParsedLimits());
  }

  /**
   * Set resource limits for an exercise
   * @POST
   * @Param(type="post", name="limits", description="A list of resource limits for the given environment and hardware group", validation="array")
   * @param string $id Identifier of the exercise
   * @param string $runtimeEnvironmentId
   * @param string $hwGroupId
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionSetLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canSetLimits($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to set limits for this exercise.");
    }

    $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
    $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);

    $oldLimits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
    if ($oldLimits === NULL) {
      throw new NotFoundException("Limits for exercise cannot be found");
    }

    $req = $this->getRequest();
    $limits = $req->getPost("limits");

    if (count($limits) === 0) {
      throw new NotFoundException("No limits specified");
    }

    // using loader load limits into internal structure which should detect formatting errors
    $exerciseLimits = $this->exerciseConfigLoader->loadExerciseLimits($limits);
    // new limits were provided, so construct new database entity
    $newLimits = new ExerciseLimits($environment, $hwGroup, (string) $exerciseLimits, $oldLimits);

    // remove old limits for corresponding environment and hwgroup and add new ones
    $exercise->removeExerciseLimits($oldLimits);
    $exercise->addExerciseLimits($newLimits);
    $this->exercises->flush();

    $this->sendSuccessResponse($newLimits->getParsedLimits());
  }
}
