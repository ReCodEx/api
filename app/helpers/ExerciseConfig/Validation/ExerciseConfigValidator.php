<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Model\Entity\Pipeline;
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
   * @param array $variablesTables
   * @throws ExerciseConfigException
   */
  private function checkEnvironments(ExerciseConfig $config, array $variablesTables) {
    if (count($config->getEnvironments()) !== count($variablesTables)) {
      throw new ExerciseConfigException("Environments in exercise configuration does not match the ones defined in exercise");
    }

    foreach ($config->getEnvironments() as $environment) {
      if (!array_key_exists($environment, $variablesTables)) {
        throw new ExerciseConfigException();
      }
    }
  }

  /**
   * @param ExerciseConfig $config
   * @throws ExerciseConfigException
   */
  private function checkPipelines(ExerciseConfig $config) {

    // define reusable check function
    $checkFunc = function (array $pipelines) {
      foreach ($pipelines as $pipeline => $pipelineVars) {
        $entity = $this->pipelines->findOneByName($pipeline);
        if (!$entity) {
          throw new ExerciseConfigException("Pipeline '$pipeline' not found");
        }
        $this->checkPipelineVariables($entity, $pipelineVars);
      }
    };

    foreach ($config->getTests() as $test) {
      // check default pipelines
      $checkFunc($test->getPipelines());

      // go through all environments in test
      foreach ($test->getEnvironments() as $envId => $environment) {
        // check pipelines in environment
        $checkFunc($environment->getPipelines());
      }
    }
  }

  /**
   * @param Pipeline $pipelineEntity
   * @param PipelineVars $pipelineVars
   */
  private function checkPipelineVariables(Pipeline $pipelineEntity, PipelineVars $pipelineVars) {
    $pipeline = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig()->getParsedPipeline());
    // @todo
  }

  /**
   * Validate exercise configuration.
   * @param ExerciseConfig $config
   * @param array $variablesTables indexed with runtime environment
   * identification and containing variables table
   * @throws ExerciseConfigException
   */
  public function validate(ExerciseConfig $config, array $variablesTables) {
    $this->checkEnvironments($config, $variablesTables);
    $this->checkPipelines($config);
  }

}
