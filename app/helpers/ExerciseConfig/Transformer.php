<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
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
   * @param ExerciseConfig $exerciseConfig
   * @return array
   */
  public function fromExerciseConfig(ExerciseConfig $exerciseConfig): array {
    $config = array();

    // create default values
    $config['default'] = array();
    foreach ($exerciseConfig->getTests() as $testId => $test) {
      $config['default'][$testId] = array();
      $config['default'][$testId]['pipelines'] = $test->getPipelines();
      $config['default'][$testId]['variables'] = $test->getVariables();
    }

    foreach ($exerciseConfig->getTests() as $testId => $test) {
      foreach ($test->getEnvironments() as $environmentId => $environment) {

        // we have never been here before, so create an entry in configuration
        // for this environment
        if (!array_key_exists($environmentId, $config)) {
          $config[$environmentId] = array();
        }

        // fill defaults into test
        $config[$environmentId][$testId] = array();

        // fill values from environment
        $environment = $test->getEnvironment($environmentId);
        $config[$environmentId][$testId]["pipelines"] = $environment->getPipelines();
        $config[$environmentId][$testId]["variables"] = $environment->getVariables();

        // if pipelines were empty use default ones
        if (empty($environment->getPipelines())) {
          $config[$environmentId][$testId]["pipelines"] = $test->getPipelines();
        }

        // if variables were empty use default ones
        if (empty($environment->getVariables())) {
          $config[$environmentId][$testId]["variables"] = $test->getVariables();
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
   * @throws ExerciseConfigException
   */
  public function toExerciseConfig(array $data): ExerciseConfig {
    if (!array_key_exists('default', $data)) {
      throw new ExerciseConfigException("Defaults was not specified");
    }

    if (count($data) < 2) {
      throw new ExerciseConfigException("No tests specified");
    }

    // parse config from format given by web-app to internal structure
    $parsedConfig = array();
    $testIds = array();
    $testsCount = 0;
    $parsedConfig[ExerciseConfig::TESTS_KEY] = array();
    $tests =& $parsedConfig[ExerciseConfig::TESTS_KEY];

    // retrieve defaults for tests
    foreach ($data['default'] as $testId => $test) {
      $tests[$testId] = array();
      $tests[$testId][Test::PIPELINES_KEY] = $test['pipelines'];
      $tests[$testId][Test::VARIABLES_KEY] = $test['variables'];
      $tests[$testId][Test::ENVIRONMENTS_KEY] = array();

      $testIds[] = $testId;
      $testsCount++;
    }
    unset($data['default']);

    // iterate through all environments
    foreach ($data as $environmentId => $environment) {
      $envTestsCount = 0;
      foreach ($environment as $testId => $test) {
        if (!in_array($testId, $testIds)) {
          throw new ExerciseConfigException("Test $testId was not specified in defaults");
        }

        $tests[$testId][Test::ENVIRONMENTS_KEY][$environmentId] = array();
        $environmentConfig =& $tests[$testId][Test::ENVIRONMENTS_KEY][$environmentId];
        $environmentConfig[Environment::PIPELINES_KEY] = array();
        $environmentConfig[Environment::VARIABLES_KEY] = array();

        // pipelines are not the same as defaults
        if ($tests[$testId][Test::PIPELINES_KEY] !== $test['pipelines']) {
          $environmentConfig[Environment::PIPELINES_KEY] = $test['pipelines'];
        }

        // variables are not the same as defaults
        if ($tests[$testId][Test::VARIABLES_KEY] !== $test['variables']) {
          $environmentConfig[Environment::VARIABLES_KEY] = $test['variables'];
        }

        $envTestsCount++;
      }

      if ($testsCount !== $envTestsCount) {
        throw new ExerciseConfigException("Tests differs from defaults in environment $environmentId");
      }
    }

    // using loader to load config into internal structure which should detect formatting errors
    return $this->loader->loadExerciseConfig($parsedConfig);
  }

}
