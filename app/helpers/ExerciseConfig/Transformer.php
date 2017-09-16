<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;

/**
 * Transformer which is able to transform configuration from internal
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
   * Transform list of Pipeline internal structure into presented array.
   * @param PipelineVars[] $pipelines
   * @return array
   */
  private function fromPipelines(array $pipelines): array {
    $pipelinesArr = array();
    foreach ($pipelines as $pipelineId => $pipeline) {
      $pipelineArr = array();
      $pipelineArr["name"] = $pipelineId;
      $pipelineArr["variables"] = array();

      foreach ($pipeline->getVariablesTable()->getAll() as $variable) {
        // do not forget to add constructed variable to pipeline
        $pipelineArr["variables"][] = $variable->toArray();
      }

      // do not forget to add pipeline into resulting array
      $pipelinesArr[] = $pipelineArr;
    }

    return $pipelinesArr;
  }

  /**
   * Transform ExerciseConfig internal structure into array which can be used
   * as return type for web-app.
   * @param ExerciseConfig $exerciseConfig
   * @return array
   */
  public function fromExerciseConfig(ExerciseConfig $exerciseConfig): array {
    $config = array();

    // prepare defaults
    $defaultEnv = array();
    $defaultEnv["name"] = "default";
    $defaultEnv["tests"] = array();
    foreach ($exerciseConfig->getTests() as $testId => $test) {
      // initialize defaults for each test
      $testArr = array();
      $testArr["name"] = $testId;
      $testArr["pipelines"] = $this->fromPipelines($test->getPipelines());

      // do not forget to add constructed test to the default
      $defaultEnv["tests"][] = $testArr;
    }
    $config[] = $defaultEnv;

    // initialize all possible environments which can be present in tests
    foreach ($exerciseConfig->getEnvironments() as $environmentId) {
      $environmentArr = array();
      $environmentArr["name"] = $environmentId;
      $environmentArr["tests"] = array();
      foreach ($exerciseConfig->getTests() as $testId => $test) {

        // initialize all tests
        $testArr = array();
        $testArr["name"] = $testId;
        $testArr["pipelines"] = array();

        // find this particular environment in test
        $environment = $test->getEnvironment($environmentId);
        if ($environment) {
          // there are specific pipelines for this environment
          if (!empty($environment->getPipelines())) {
            $testArr["pipelines"] = $this->fromPipelines($environment->getPipelines());
          }
        }

        // do not forget to add constructed test to environment
        $environmentArr["tests"][] = $testArr;
      }
      $config[] = $environmentArr;
    }

    return $config;
  }

  /**
   * Transform data to pipeline internal structured array.
   * @param array $data
   * @return array
   */
  private function toPipeline(array $data): array {
    $pipelineArr = array();
    $pipelineArr[PipelineVars::NAME_KEY] = $data["name"];
    $pipelineArr[PipelineVars::VARIABLES_KEY] = array();

    foreach ($data["variables"] as $variable) {
      // do not forget to add constructed variable to pipeline
      $pipelineArr[PipelineVars::VARIABLES_KEY][] = $variable;
    }

    return $pipelineArr;
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
      if ($environment["name"] !== "default") {
        continue;
      }

      $defaultFound = true;

      foreach ($environment["tests"] as $test) {
        $testId = $test["name"];

        $testArr = array();
        $testArr[Test::PIPELINES_KEY] = array();

        foreach ($test["pipelines"] as $pipeline) {
          $testArr[Test::PIPELINES_KEY][] = $this->toPipeline($pipeline);
        }

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
    if (count($data) == 0) {
      throw new ExerciseConfigException("No environments specified");
    }

    // iterate through all environments
    foreach ($data as $environment) {
      $envTestsCount = 0;
      $environmentId = $environment["name"];
      $environments[] = $environmentId;

      foreach ($environment["tests"] as $test) {
        $testId = $test["name"];

        if (!in_array($testId, $testIds)) {
          throw new ExerciseConfigException("Test '$testId' was not specified in defaults");
        }

        $environmentConfig = array();
        $environmentConfig[Environment::PIPELINES_KEY] = array();
        foreach ($test["pipelines"] as $pipeline) {
          $environmentConfig[Environment::PIPELINES_KEY][] = $this->toPipeline($pipeline);
        }

        // pipelines are the same as the defaults
        if ($tests[$testId][Test::PIPELINES_KEY] === $environmentConfig[Environment::PIPELINES_KEY]) {
          $environmentConfig[Environment::PIPELINES_KEY] = array();
        }

        // collected environment has to be added to config
        $parsedConfig[ExerciseConfig::TESTS_KEY][$testId][Test::ENVIRONMENTS_KEY][$environmentId] = $environmentConfig;
        $envTestsCount++;
      }

      if ($testsCount !== $envTestsCount) {
        throw new ExerciseConfigException("Tests differs from defaults in environment '$environmentId'");
      }
    }

    // all visited environments has to be written into exercise config
    $parsedConfig[ExerciseConfig::ENVIRONMENTS_KEY] = array_unique($environments);

    // using loader to load config into internal structure which should detect formatting errors
    return $this->loader->loadExerciseConfig($parsedConfig);
  }

}
