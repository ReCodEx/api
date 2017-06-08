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

    // prepare environments array with default
    $environments = array_merge([ "default" ], $exerciseConfig->getEnvironments());

    // initialize all possible environments which can be present in tests
    // with respective default values
    foreach ($environments as $environmentId) {
      $environment = array();
      $environment['name'] = $environmentId;
      $environment['tests'] = array();
      foreach ($exerciseConfig->getTests() as $testId => $test) {
        // initialize environment for each test with defaults
        $tests = array();
        $tests['name'] = $testId;
        $tests['pipelines'] = $test->getPipelines();
        $tests['variables'] = $test->getVariables();
        $environment['tests'][] = $tests;
      }
      $config[] = $environment;
    }

    // go through defined tests and environments and fill values if present
    /*foreach ($exerciseConfig->getTests() as $testId => $test) {
      foreach ($test->getEnvironments() as $environmentId => $environment) {

        $environment = $test->getEnvironment($environmentId);

        // there are specific pipelines for this environment
        if (!empty($environment->getPipelines())) {
          $config[$environmentId][$testId]["pipelines"] = $environment->getPipelines();
        }

        // there are specific variables for this environment
        if (!empty($environment->getVariables())) {
          $config[$environmentId][$testId]["variables"] = $environment->getVariables();
        }
      }
    }*/

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

    // helper variables
    $testIds = array();
    $testsCount = 0;
    $environments = array();

    // parse config from format given by web-app to internal structure
    $parsedConfig = array();
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
      $environments[] = $environmentId;

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

    // all visited environments has to be written into exercise config
    $parsedConfig[ExerciseConfig::ENVIRONMENTS_KEY] = array_unique($environments);

    // using loader to load config into internal structure which should detect formatting errors
    return $this->loader->loadExerciseConfig($parsedConfig);
  }

}
