<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Model\Repository\Exercises;
use App\Helpers\JobConfig;
use App\Model\Repository\RuntimeConfigs;
use App\Model\Repository\ReferenceSolutionEvaluations;
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
   * @var JobConfig\Storage
   * @inject
   */
  public $jobConfigs;

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
   * Get a description of resource limits for an exercise
   * @GET
   * @UserIsAllowed(exercises="view-limits")
   * @param string $id Identifier of the exercise
   */
  public function actionGetLimits(string $id) {

    // @todo: rewrite

    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    if (!$exercise->canModifyDetail($user)) {
      throw new ForbiddenRequestException("You are not allowed to get limits for this exercise.");
    }

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

    // @todo: rewrite

    $user = $this->getCurrentUser();
    $exercise = $this->exercises->findOrThrow($id);
    $exerciseRuntimeConfigsIds = $exercise->getRuntimeConfigsIds();

    if (!$exercise->canModifyDetail($user)) {
      throw new ForbiddenRequestException("You are not allowed to get limits for this exercise.");
    }

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
