<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Transformer;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseLimits;
use App\Model\Repository\Exercises;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\ReferenceSolutionEvaluations;
use App\Model\Repository\RuntimeEnvironments;

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
   * Get a basic exercise high level configuration.
   * @GET
   * @UserIsAllowed(exercises="update")
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionGetConfiguration(string $id) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->canModifyDetail($user)) {
      throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
    }

    $exerciseConfig = $exercise->getExerciseConfig();
    if ($exerciseConfig === NULL) {
      throw new NotFoundException("Configuration for the exercise not exists");
    }

    $parsedConfig = $this->exerciseConfigLoader->loadExerciseConfig($exerciseConfig->getParsedConfig());

    // create configuration array which will be returned
    $config = $this->exerciseConfigTransformer->fromExerciseConfig($exercise, $parsedConfig);
    $this->sendSuccessResponse($config);
  }

  /**
   * Set basic exercise configuration
   * @POST
   * @UserIsAllowed(exercises="update")
   * @Param(type="post", name="config", description="A list of basic high level exercise configuration", validation="array")
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionSetConfiguration(string $id) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->canModifyDetail($user)) {
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

    // remove old limits for corresponding environment and hwgroup and add new ones
    $exercise->setExerciseConfig($newConfig);
    $this->exercises->flush();

    $this->sendSuccessResponse("OK");
  }

  /**
   * Get a description of resource limits for an exercise
   * @GET
   * @UserIsAllowed(exercises="view-limits")
   * @param string $id Identifier of the exercise
   * @param string $runtimeEnvironmentId
   * @param string $hwGroupId
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionGetLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->canModifyDetail($user)) {
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
   * @UserIsAllowed(exercises="set-limits")
   * @Param(type="post", name="limits", description="A list of resource limits for the given environment and hardware group", validation="array")
   * @param string $id Identifier of the exercise
   * @param string $runtimeEnvironmentId
   * @param string $hwGroupId
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionSetLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId) {
    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->canModifyDetail($user)) {
      throw new ForbiddenRequestException("You are not allowed to get limits for this exercise.");
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
