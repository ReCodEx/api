<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\JobConfigStorageException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Transformer;
use App\Helpers\ExerciseConfig\Validator;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseLimits;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Repository\Exercises;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\Pipelines;
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
   * @var Pipelines
   * @inject
   */
  public $pipelines;

  /**
   * @var Loader
   * @inject
   */
  public $exerciseConfigLoader;

  /**
   * @var Helper
   * @inject
   */
  public $exerciseConfigHelper;

  /**
   * @var Validator
   * @inject
   */
  public $configValidator;

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
   * Needed change of ExerciseConfig after update of environment configurations.
   * Flush of the database is not performed!
   * @param Exercise $exercise
   */
  private function updateEnvironmentsInExerciseConfig(Exercise $exercise) {
    $exerciseEnvironments = $exercise->getRuntimeEnvironmentsIds();
    $exerciseConfig = $this->exerciseConfigLoader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());

    // go through new environments config and add potentially new environment to ExerciseConfig
    foreach ($exerciseEnvironments as $environmentId) {
      if (in_array($environmentId, $exerciseConfig->getEnvironments())) {
        continue;
      }

      // environment can be added only at the top level, in the tests there
      // should be assigned default pipeline values during transformation
      $exerciseConfig->addEnvironment($environmentId);
    }

    // delete unused environments from ExerciseConfig
    foreach ($exerciseConfig->getEnvironments() as $environmentId) {
      if (in_array($environmentId, $exerciseEnvironments)) {
        continue;
      }

      // environment needs to be deleted from top level, but also all tests
      // have to be run through and optionally environments should be deleted
      $exerciseConfig->removeEnvironment($environmentId);
      foreach ($exerciseConfig->getTests() as $test) {
        $test->removeEnvironment($environmentId);
      }
    }

    // finally write changes into exercise entity
    $configEntity = new ExerciseConfig((string) $exerciseConfig, $this->getCurrentUser(), $exercise->getExerciseConfig());
    $exercise->setExerciseConfig($configEntity);
  }

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
   * @Param(type="post", name="environmentConfigs", validation="array", description="Environment configurations for the exercise")
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
    $environmentConfigs = $req->getPost("environmentConfigs");
    $configs = [];

    // configurations cannot be empty
    if (count($environmentConfigs) == 0) {
      throw new InvalidArgumentException("No entry for runtime configurations given.");
    }

    $runtimeEnvironments = new ArrayCollection;
    // go through given configurations and construct database entities
    foreach ($environmentConfigs as $environmentConfig) {
      $environmentId = $environmentConfig["runtimeEnvironmentId"];
      $environment = $this->runtimeEnvironments->findOrThrow($environmentId);

      // add runtime environment into resulting environments
      $runtimeEnvironments->add($environment);

      // check for duplicate environments
      if (array_key_exists($environmentId, $configs)) {
        throw new InvalidArgumentException("Duplicate entry for configuration '$environmentId''");
      }

      // load variables table for this runtime configuration
      $varTableArray = array_key_exists("variablesTable", $environmentConfig) ? $environmentConfig["variablesTable"] : array();
      $variablesTable = $this->exerciseConfigLoader->loadVariablesTable($varTableArray);

      // create all new runtime configuration
      $config = new ExerciseEnvironmentConfig(
        $environment,
        (string) $variablesTable,
        $this->getCurrentUser(),
        $exercise->getExerciseEnvironmentConfigByEnvironment($environment)
      );

      $configs[$environmentId] = $config;
    }

    // make changes and updates to database entity
    $exercise->setRuntimeEnvironments($runtimeEnvironments);
    $this->exercises->replaceEnvironmentConfigs($exercise, $configs, FALSE);
    $this->updateEnvironmentsInExerciseConfig($exercise);

    // flush database changes and return successful response
    $this->exercises->flush();
    $this->sendSuccessResponse("OK");
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
      // should not be reached...
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

    // validate new exercise config
    $this->configValidator->validateExerciseConfig($exercise, $exerciseConfig);

    // new config was provided, so construct new database entity
    $newConfig = new ExerciseConfig((string) $exerciseConfig, $this->getCurrentUser(), $oldConfig);

    // set new exercise configuration into exercise and flush changes
    $exercise->setExerciseConfig($newConfig);
    $this->exercises->flush();

    $config = $this->exerciseConfigTransformer->fromExerciseConfig($exerciseConfig);
    $this->sendSuccessResponse($config);
  }

  /**
   * Get variables for exercise configuration which are derived from given
   * pipelines and runtime environment.
   * @POST
   * @param string $id Identifier of the exercise
   * @Param(type="post", name="runtimeEnvironmentId", validation="string:1..", description="Environment identifier", required=FALSE)
   * @Param(type="post", name="pipelinesIds", validation="array", description="Identifiers of selected pipelines for one test")
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionGetVariablesForExerciseConfig(string $id) {
    // get request data
    $req = $this->getRequest();
    $runtimeEnvironmentId = $req->getPost("runtimeEnvironmentId");
    $pipelinesIds = $req->getPost("pipelinesIds");

    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to get variables for this exercise configuration.");
    }

    // prepare pipeline configurations
    $pipelines = [];
    foreach ($pipelinesIds as $pipelineId) {
      $pipelineConfig = $this->pipelines->findOrThrow($pipelineId)->getPipelineConfig();
      $pipelines[$pipelineId] = $this->exerciseConfigLoader->loadPipeline($pipelineConfig->getParsedPipeline());
    }

    // prepare environment configuration if needed
    if ($runtimeEnvironmentId !== NULL) {
        $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
        $environmentConfig = $exercise->getExerciseEnvironmentConfigByEnvironment($environment);
        $environmentVariables = $this->exerciseConfigLoader->loadVariablesTable($environmentConfig->getParsedVariablesTable());
    } else {
        $environmentVariables = new VariablesTable();
    }

    // compute result and send it back
    $result = $this->exerciseConfigHelper->getVariablesForExercise($pipelines, $environmentVariables);
    $this->sendSuccessResponse($result);
  }

  public function actionGetLimits() {
    // @todo
  }

  public function actionSetLimits() {
    // @todo
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
  public function actionGetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canViewLimits($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to get limits for this exercise.");
    }

    $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
    $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);

    // check if exercise defines requested environment
    if (!$exercise->getRuntimeEnvironments()->contains($environment)) {
      throw new NotFoundException("Specified environment '$runtimeEnvironmentId' not defined for this exercise");
    }

    $limits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
    if ($limits === NULL) {
      // there are no specified limits for this combination of environments
      // and hwgroup yet, so return empty array
      $limits = array();
    } else {
      $limits = $limits->getParsedLimits();
    }

    $this->sendSuccessResponse($limits);
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
  public function actionSetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canSetLimits($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to set limits for this exercise.");
    }

    $limits = $this->getRequest()->getPost("limits");
    $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
    $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);

    // check if exercise defines requested environment
    if (!$exercise->getRuntimeEnvironments()->contains($environment)) {
      throw new NotFoundException("Specified environment '$runtimeEnvironmentId' not defined for this exercise");
    }

    // using loader load limits into internal structure which should detect formatting errors
    $exerciseLimits = $this->exerciseConfigLoader->loadExerciseLimits($limits);
    // validate new limits
    $this->configValidator->validateExerciseLimits($exercise, $exerciseLimits, $runtimeEnvironmentId);

    // new limits were provided, so construct new database entity
    $oldLimits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
    $newLimits = new ExerciseLimits($environment, $hwGroup, (string) $exerciseLimits, $this->getCurrentUser(), $oldLimits);

    // remove old limits for corresponding environment and hwgroup and add new ones
    // also do not forget to set hwgroup to exercise
    $exercise->removeExerciseLimits($oldLimits);
    $exercise->addExerciseLimits($newLimits);
    $exercise->removeHardwareGroup($hwGroup); // if there was one before
    $exercise->addHardwareGroup($hwGroup);
    $this->exercises->flush();

    $this->sendSuccessResponse($newLimits->getParsedLimits());
  }

  /**
   * Remove resource limits for an exercise
   * @DELETE
   * @param string $id Identifier of the exercise
   * @param string $runtimeEnvironmentId
   * @param string $hwGroupId
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionRemoveHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canSetLimits($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to set limits for this exercise.");
    }

    $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
    $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);

    // find requested limits
    $limits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
    if (!$limits) {
      throw new NotFoundException("Specified limits not found");
    }

    // make changes persistent
    $exercise->removeExerciseLimits($limits);
    $exercise->removeHardwareGroup($hwGroup);
    $this->exercises->flush();

    $this->sendSuccessResponse("OK");
  }

}
