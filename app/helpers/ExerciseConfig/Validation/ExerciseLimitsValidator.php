<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Environment;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\Limits;
use App\Helpers\ExerciseConfig\Loader;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise limits validation service.
 */
class ExerciseLimitsValidator {

  /**
   * @var Pipelines
   */
  private $pipelines;

  /**
   * @var Loader
   */
  private $loader;

  /**
   * ExerciseConfigValidator constructor.
   * @param Pipelines $pipelines
   * @param Loader $loader
   */
  public function __construct(Pipelines $pipelines, Loader $loader) {
    $this->pipelines = $pipelines;
    $this->loader = $loader;
  }


  /**
   * Validate exercise limits.
   * @param ExerciseLimits $exerciseLimits
   * @param ExerciseConfig $exerciseConfig
   * @param string $environmentId
   * @throws ExerciseConfigException
   */
  public function validate(ExerciseLimits $exerciseLimits, ExerciseConfig $exerciseConfig, string $environmentId) {
    $loadedPipelines = [];

    foreach ($exerciseLimits->getLimitsArray() as $testId => $firstLevel) {
      $test = $exerciseConfig->getTest($testId);

      if ($test === NULL) {
        throw new ExerciseConfigException(sprintf(
          "Test %s is not present in the exercise configuration",
          $testId
        ));
      }

      $environment = $test->getEnvironment($environmentId);

      $pipelineNames = array_keys(
        count($environment->getPipelines()) === 0
        ? $test->getPipelines()
        : $environment->getPipelines()
      );

      $environmentPipelines = [];

      foreach ($pipelineNames as $name) {
        $this->loadPipeline($name, $loadedPipelines);
        $environmentPipelines[$name] = $loadedPipelines[$name];
      }

      if ($test === NULL) {
        throw new ExerciseConfigException(sprintf(
          "Test with id %s does not exist in the exercise configuration",
          $testId
        ));
      }

      foreach ($firstLevel as $pipelineId => $secondLevel) {
        if (!array_key_exists($pipelineId, $environmentPipelines)) {
          throw new ExerciseConfigException(sprintf(
            "Pipeline %s is not used in test %s, but it is present in limit configuration",
            $pipelineId,
            $testId
          ));
        }

        foreach ($secondLevel as $boxId => $limits) {
          $box = $environmentPipelines[$pipelineId]->get($boxId);

          if ($box === NULL) {
            throw new ExerciseConfigException(sprintf(
              "Box with id %s does not exist in pipeline %s",
              $boxId,
              $pipelineId
            ));
          }

          // Make sure that no invalid limit identifiers (types) are used
          foreach (array_keys($limits) as $limit) {
            if (!in_array($limit, Limits::VALID_LIMITS)) {
              throw new ExerciseConfigException(sprintf(
                "Unknown limit identifier %s (allowed limits are %s)",
                $limit,
                implode(", ", Limits::VALID_LIMITS)
              ));
            }
          }
        }
      }
    }
  }

  /**
   * @param $name
   * @param $loadedPipelines
   * @throws ExerciseConfigException
   */
  private function loadPipeline($name, &$loadedPipelines): void
  {
    if (!array_key_exists($name, $loadedPipelines)) {
      $pipelineEntity = $this->pipelines->get($name);

      if ($pipelineEntity === NULL) {
        throw new ExerciseConfigException(sprintf(
          "Pipeline with id %s does not exist",
          $name
        ));
      }

      $loadedPipelines[$name] = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig()->getParsedPipeline());
    }
  }
}
