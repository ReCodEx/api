<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Variable;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise configuration validation service.
 */
class ExerciseConfigValidator {

  /**
   * @var Loader
   */
  private $loader;

  /**
   * @var Pipelines
   */
  private $pipelines;

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
   * @param ExerciseConfig $config
   * @param Exercise $exercise
   * @throws ExerciseConfigException
   */
  private function checkEnvironments(ExerciseConfig $config, Exercise $exercise) {
    $envSpecificConfigs = $exercise->getExerciseEnvironmentConfigs();

    if (count($config->getEnvironments()) !== count($envSpecificConfigs)) {
      throw new ExerciseConfigException("The number of entries in environment-specific configuration differs from the number of allowed environments");
    }

    /** @var string $environment */
    foreach ($config->getEnvironments() as $environment) {
      $matchingConfigExists = $envSpecificConfigs->exists(
        function ($key, ExerciseEnvironmentConfig $envConfig) use ($environment) {
          return $envConfig->getRuntimeEnvironment()->getId() === $environment;
        }
      );

      if (!$matchingConfigExists) {
        throw new ExerciseConfigException("Environment $environment not found in environment-specific configuration");
      }
    }
  }

  /**
   * @param ExerciseConfig $config
   * @throws ExerciseConfigException
   */
  private function checkPipelines(ExerciseConfig $config) {
    foreach ($config->getTests() as $test) {
      // check default pipelines
      $this->checkPipelinesSection($test->getPipelines());

      // go through all environments in test
      foreach ($test->getEnvironments() as $envId => $environment) {
        // check pipelines in environment
        $this->checkPipelinesSection($environment->getPipelines(), $envId);
      }
    }
  }

  /**
   * @param ExerciseConfig $config
   * @param array $pipelines
   * @param string $environment
   * @throws ExerciseConfigException
   */
  private function checkPipelinesSection(ExerciseConfig $config, array $pipelines, ?string $environment = NULL) {
    /**
     * @var string $pipelineId
     * @var PipelineVars $pipelineVars
     */
    foreach ($pipelines as $pipelineId => $pipelineVars) {
      $pipelineEntity = $this->pipelines->get($pipelineId);
      if ($pipelineEntity === NULL) {
        throw new ExerciseConfigException("Pipeline '$pipelineId' not found");
      }

      $pipeline = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig()->getParsedPipeline());
      $dataInBoxes = $pipeline->getDataInBoxes();
      $inBoxNames = array_map(function (Box $box) { return $box->getName(); }, $dataInBoxes);
      $variables = $pipelineVars->getVariablesTable();
      $variableNames = $variables !== NULL
        ? array_map(function (Variable $variable) { return $variable->getName(); }, $variables->getAll())
        : [];

      /** @var Variable $variable */
      foreach ($variableNames as $variable) {
        if (!in_array($variable, $inBoxNames)) {
          throw new ExerciseConfigException(sprintf(
            "Variable '%s' is redundant in pipeline %s, environment %s",
            $variable,
            $pipelineId,
            $environment ?? "default"
          ));
        }

        $inBoxNames = array_filter($inBoxNames, function (string $name) use ($variable) {
          return $name !== $variable;
        });
      }

      if (count($inBoxNames) > 0) {
        throw new ExerciseConfigException(sprintf(
          "Missing values for variables: %s (pipeline %s)",
          implode(", ", $inBoxNames),
          $pipelineId
        ));
      }
    }
  }

  /**
   * Validate exercise configuration.
   * @param ExerciseConfig $config
   * @param Exercise $exercise
   */
  public function validate(ExerciseConfig $config, Exercise $exercise) {
    $this->checkEnvironments($config, $exercise);
    $this->checkPipelines($config);
  }

}
