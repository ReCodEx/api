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
    $environments = array_merge([ 'default' ], $exerciseConfig->getEnvironments());

    // initialize all possible environments which can be present in tests
    // with respective default values
    foreach ($environments as $environmentId) {
      $environment = array();
      $environment['name'] = $environmentId;
      $environment['tests'] = array();
      foreach ($exerciseConfig->getTests() as $testId => $test) {
        // initialize environment for each test with defaults
        $testArr = array();
        $testArr['name'] = $testId;
        $testArr['pipelines'] = $test->getPipelines();
        $testArr['variables'] = $test->getVariables();
        $environment['tests'][] = $testArr;
      }
      $config[] = $environment;
    }

    // go through defined tests and environments and fill values if present
    foreach ($config as $envIndex => $environmentArr) {
      foreach ($environmentArr['tests'] as $testIndex => $testArr) {
        $test = $exerciseConfig->getTest($testArr['name']);
        $environment = $test->getEnvironment($environmentArr['name']);

        if ($environment) {

          // there are specific pipelines for this environment
          if (!empty($environment->getPipelines())) {
            $config[$envIndex]['tests'][$testIndex]['pipelines'] = $environment->getPipelines();
          }

          // there are specific variables for this environment
          if (!empty($environment->getVariables())) {
            $config[$envIndex]['tests'][$testIndex]["variables"] = $environment->getVariables();
          }
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

    // helper variables
    $testIds = array();
    $testsCount = 0;
    $environments = array();
    $defaultFound = false;

    // parse config from format given by web-app to internal structure
    $parsedConfig = array();
    $tests = array();

    // find and retrieve defaults for tests
    foreach ($data as $envIndex => $environment) {
      if ($environment['name'] !== 'default') {
        continue;
      }

      $defaultFound = true;

      foreach ($environment['tests'] as $test) {
        $testId = $test['name'];

        $testArr = array();
        $testArr[Test::PIPELINES_KEY] = $test['pipelines'];
        $testArr[Test::VARIABLES_KEY] = $test['variables'];
        $testArr[Test::ENVIRONMENTS_KEY] = array();

        $tests[$testId] = $testArr;
        $testIds[] = $testId;
        $testsCount++;
      }

      // unset default environment
      unset($data[$envIndex]);
      break;
    }
    $parsedConfig[ExerciseConfig::TESTS_KEY] = $tests;

    // additional checks
    if (!$defaultFound) {
      throw new ExerciseConfigException("Defaults was not specified");
    }
    if (count($data) < 2) {
      throw new ExerciseConfigException("No tests specified");
    }

    // iterate through all environments
    foreach ($data as $environment) {
      $envTestsCount = 0;
      $environmentId = $environment['name'];
      $environments[] = $environmentId;

      foreach ($environment['tests'] as $test) {
        $testId = $test['name'];

        if (!in_array($testId, $testIds)) {
          throw new ExerciseConfigException("Test $testId was not specified in defaults");
        }

        $environmentConfig = array();
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

        // collected environment has to be added to config
        $parsedConfig[ExerciseConfig::TESTS_KEY][$testId][Test::ENVIRONMENTS_KEY][$environmentId] = $environmentConfig;
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
