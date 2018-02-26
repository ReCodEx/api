<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Transformer;
use App\Helpers\ExerciseConfig\Updater;
use App\Helpers\ExerciseConfig\Validator;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\ExerciseConfig\ExerciseConfigChecker;
use App\Helpers\ExerciseRestrictionsConfig;
use App\Helpers\ScoreCalculatorAccessor;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseLimits;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Entity\ExerciseTest;
use App\Model\Repository\Exercises;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\Pipelines;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Repository\RuntimeEnvironments;
use App\Security\ACL\IExercisePermissions;
use Doctrine\Common\Collections\ArrayCollection;
use Nette\Utils\Arrays;


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
   * @var Updater
   * @inject
   */
  public $exerciseConfigUpdater;

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
   * @var ReferenceSolutionSubmissions
   * @inject
   */
  public $referenceSolutionEvaluations;

  /**
   * @var IExercisePermissions
   * @inject
   */
  public $exerciseAcl;

  /**
   * @var ScoreCalculatorAccessor
   * @inject
   */
  public $calculators;

  /**
   * @var ExerciseRestrictionsConfig
   * @inject
   */
  public $exerciseRestrictionsConfig;

  /**
   * @var ExerciseConfigChecker
   * @inject
   */
  public $configChecker;

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
   * @throws ExerciseConfigException
   * @throws NotFoundException
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
      $oldConfig = $exercise->getExerciseEnvironmentConfigByEnvironment($environment);
      $config = new ExerciseEnvironmentConfig(
        $environment,
        (string) $variablesTable,
        $this->getCurrentUser(),
        $oldConfig
      );

      // validation of newly create environment config
      $this->configValidator->validateEnvironmentConfig($exercise, $variablesTable);

      $configs[$environmentId] = !$config->equals($oldConfig) ? $config: $oldConfig;
    }

    // make changes and updates to database entity
    $exercise->updatedNow();
    $exercise->setRuntimeEnvironments($runtimeEnvironments);
    $this->exercises->replaceEnvironmentConfigs($exercise, $configs, false);
    $this->exerciseConfigUpdater->environmentsUpdated($exercise, $this->getCurrentUser(), false);

    // flush database changes and return successful response
    $this->exercises->flush();

    $this->configChecker->check($exercise);
    $this->exercises->flush();
    $this->sendSuccessResponse("OK");
  }

  /**
   * Get a basic exercise high level configuration.
   * @GET
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws ExerciseConfigException
   */
  public function actionGetConfiguration(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
    }

    $exerciseConfig = $exercise->getExerciseConfig();
    if ($exerciseConfig === null) {
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
   * @throws NotFoundException
   * @throws ExerciseConfigException
   */
  public function actionSetConfiguration(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
    }

    $oldConfig = $exercise->getExerciseConfig();
    if ($oldConfig === null) {
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

    if (!$newConfig->equals($oldConfig)) {
      // set new exercise configuration into exercise and flush changes
      $exercise->updatedNow();
      $exercise->setExerciseConfig($newConfig);
      $this->exercises->flush();

      $this->configChecker->check($exercise);
      $this->exercises->flush();
    }

    $config = $this->exerciseConfigTransformer->fromExerciseConfig($exerciseConfig);
    $this->sendSuccessResponse($config);
  }

  /**
   * Get variables for exercise configuration which are derived from given
   * pipelines and runtime environment.
   * @POST
   * @param string $id Identifier of the exercise
   * @Param(type="post", name="runtimeEnvironmentId", validation="string:1..", description="Environment identifier", required=false)
   * @Param(type="post", name="pipelinesIds", validation="array", description="Identifiers of selected pipelines for one test")
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws ExerciseConfigException
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
    if ($runtimeEnvironmentId !== null) {
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

  /**
   * Get a description of resource limits for an exercise for given hwgroup
   * @GET
   * @param string $id Identifier of the exercise
   * @param string $runtimeEnvironmentId
   * @param string $hwGroupId
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws ExerciseConfigException
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
    if ($limits === null) {
      // there are no specified limits for this combination of environments
      // and hwgroup yet, so return empty array
      $limits = array();
    } else {
      $limits = $limits->getParsedLimits();
    }

    $this->sendSuccessResponse($limits);
  }

  /**
   * Set resource limits for an exercise for given hwgroup.
   * @POST
   * @Param(type="post", name="limits", description="A list of resource limits for the given environment and hardware group", validation="array")
   * @param string $id Identifier of the exercise
   * @param string $runtimeEnvironmentId
   * @param string $hwGroupId
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws ExerciseConfigException
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
    $this->configValidator->validateExerciseLimits($exercise, $exerciseLimits);

    // new limits were provided, so construct new database entity
    $oldLimits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
    $newLimits = new ExerciseLimits($environment, $hwGroup, (string) $exerciseLimits, $this->getCurrentUser(), $oldLimits);

    // remove old limits for corresponding environment and hwgroup and add new ones
    // also do not forget to set hwgroup to exercise
    $exercise->removeExerciseLimits($oldLimits); // if there were ones before
    $exercise->addExerciseLimits($newLimits);
    $exercise->removeHardwareGroup($hwGroup); // if there was one before
    $exercise->addHardwareGroup($hwGroup);

    // update and return
    $exercise->updatedNow();
    $this->exercises->flush();

    // check exercise configuration
    $this->configChecker->check($exercise);
    $this->exercises->flush();
    $this->sendSuccessResponse($newLimits->getParsedLimits());
  }

  /**
   * Remove resource limits of given hwgroup from an exercise.
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

    $exercise->updatedNow();
    $this->exercises->flush();

    // check exercise configuration
    $this->configChecker->check($exercise);
    $this->exercises->flush();
    $this->sendSuccessResponse("OK");
  }

  /**
   * Get score configuration for exercise based on given identification.
   * @GET
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetScoreConfig(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canViewScoreConfig($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to view score config for this exercise.");
    }

    // yes... it is just easy as that
    $this->sendSuccessResponse($exercise->getScoreConfig());
  }

  /**
   * Set score configuration for exercise.
   * @POST
   * @Param(type="post", name="scoreConfig", validation="string", description="A configuration of the score calculator (the exact format depends on the calculator assigned to the exercise)")
   * @param string $id Identifier of the exercise
   * @throws ExerciseConfigException
   * @throws ForbiddenRequestException
   */
  public function actionSetScoreConfig(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canSetScoreConfig($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to set score config for this exercise.");
    }

    $req = $this->getRequest();
    $config = $req->getPost("scoreConfig");

    // validate score configuration
    $calculator = $this->calculators->getCalculator($exercise->getScoreCalculator());
    if (!$calculator->isScoreConfigValid($config)) {
      throw new ExerciseConfigException("Exercise score configuration is not valid");
    }

    $exercise->updatedNow();
    $exercise->setScoreConfig($config);
    $this->exercises->flush();

    // check exercise configuration
    $this->configChecker->check($exercise);
    $this->exercises->flush();
    $this->sendSuccessResponse($exercise->getScoreConfig());
  }

  /**
   * Get tests for exercise based on given identification.
   * @GET
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetTests(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canViewDetail($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to view tests for this exercise.");
    }

    // Get to da responsa!
    $this->sendSuccessResponse($exercise->getExerciseTests()->getValues());
  }

  /**
   * Set tests for exercise based on given identification.
   * @POST
   * @param string $id Identifier of the exercise
   * @Param(type="post", name="tests", validation="array", description="An array of tests which will belong to exercise")
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws ExerciseConfigException
   */
  public function actionSetTests(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to set tests for this exercise.");
    }

    $req = $this->getRequest();
    $tests = $req->getPost("tests");

    $newTests = [];
    foreach ($tests as $test) {
      if (!array_key_exists("name", $test)) {
        throw new InvalidArgumentException("tests", "name item not found in particular test");
      }

      $name = $test["name"];
      $id = Arrays::get($test, "id", null);
      $description = Arrays::get($test, "description", "");

      $testEntity = $id ? $exercise->getExerciseTestById($id) : null;
      if ($testEntity === null) {
        // new exercise test was requested to be created
        if ($exercise->getExerciseTestByName($name)) {
          throw new InvalidArgumentException("tests", "given test name '$name' is already taken");
        }

        $testEntity = new ExerciseTest(trim($name), $description, $this->getCurrentUser());
      } else {
        // update of existing exercise test with all appropriate fields
        $testEntity->setName(trim($name));
        $testEntity->setDescription($description);
        $testEntity->updatedNow();
      }


      if (array_key_exists($name, $newTests)) {
        throw new InvalidArgumentException("tests", "two tests with the same name '$name' were specified");
      }
      $newTests[$name] = $testEntity;
    }

    $testCountLimit = $this->exerciseRestrictionsConfig->getTestCountLimit();
    if (count($newTests) > $testCountLimit) {
      throw new InvalidArgumentException(
        "tests",
        "The number of tests exceeds the configured limit ($testCountLimit)"
      );
    }

    // clear old tests and set new ones
    $exercise->getExerciseTests()->clear();
    $exercise->setExerciseTests(new ArrayCollection($newTests));

    // update exercise configuration and test in here
    $this->exerciseConfigUpdater->testsUpdated($exercise, $this->getCurrentUser(), false);

    $exercise->updatedNow();
    $this->exercises->flush();

    $this->configChecker->check($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($exercise->getExerciseTests()->getValues());
  }

}
