<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\Exercise;

/**
 * Transformer which is able to transfrom configuration from internal
 * representation to the one which is presented to users.
 */
class Transformer {

  /**
   * @var Loader
   */
  private $loader;

  /**
   * Transformer constructor.
   * @param Loader $loader
   */
  public function __construct(Loader $loader) {
    $this->loader = $loader;
  }

  /**
   * Transform ExerciseConfig internal structure into array which can be used
   * as return type for web-app.
   * @param Exercise $exercise
   * @param ExerciseConfig $exerciseConfig
   * @return array
   */
  public function fromExerciseConfig(Exercise $exercise, ExerciseConfig $exerciseConfig): array {
    $config = array();

    // create default values
    $config['default'] = array();
    foreach ($exerciseConfig->getTests() as $testId => $test) {
      $config['default'][$testId] = array();
      $config['default'][$testId]['pipelines'] = $test->getPipelines();
      $config['default'][$testId]['variables'] = $test->getVariables();
    }

    // prepare buckets for runtime environment of exercise
    foreach ($exercise->getRuntimeConfigs() as $runtimeConfig) {
      $environmentId = $runtimeConfig->getRuntimeEnvironment()->getId();
      $config[$environmentId] = array();

      // fill tests into environment
      foreach ($exerciseConfig->getTests() as $testId => $test) {
        $config[$environmentId][$testId] = array();
        $config[$environmentId][$testId]["pipelines"] = $test->getPipelines();
        $config[$environmentId][$testId]["variables"] = $test->getVariables();

        $environment = $test->getEnvironment($environmentId);
        if ($environment) {
          $config[$environmentId][$testId]["pipelines"] = $environment->getPipelines();
          $config[$environmentId][$testId]["variables"] = $environment->getVariables();
        }
      }
    }

    return $config;
  }

  /**
   * Transform given data to ExerciseConfig internal structure and check if the
   * formatting and invariants are correct.
   * @param array $data
   * @return ExerciseConfig
   * @throws ForbiddenRequestException
   */
  public function toExerciseConfig(array $data): ExerciseConfig {
    if (!array_key_exists('default', $data)) {
      throw new ForbiddenRequestException("Defaults was not specified");
    }

    if (count($data) < 2) {
      throw new ForbiddenRequestException("No tests specified");
    }

    // parse config from format given by web-app to internal structure
    $parsedConfig = array();
    $tests = $parsedConfig[ExerciseConfig::TESTS_KEY] = array();

    // retrieve defaults for tests
    foreach ($data['default'] as $testId => $test) {
      $tests[$testId] = array();
      $tests[$testId][Test::PIPELINES_KEY] = $test['pipelines'];
      $tests[$testId][Test::VARIABLES_KEY] = $test['variables'];
      $tests[$testId][Test::ENVIRONMENTS_KEY] = array();
    }
    unset($data['default']);

    // @todo: check if all test are present in all environments

    // iterate through all environments
    foreach ($data as $environmentId => $environment) {
      ;
    }

    // using loader to load config into internal structure which should detect formatting errors
    return $this->loader->loadExerciseConfig($parsedConfig);
  }

}
