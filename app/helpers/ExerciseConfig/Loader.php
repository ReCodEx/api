<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;

/**
 * Loader service which is able to load exercise configuration into internal
 * holders. Given data are checked against mandatory fields and in case of error
 * exception is thrown.
 */
class Loader {

  public function loadVariable($data): Variable {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise variable is not array");
    }

    $variable = new Variable;

    if (!isset($data[Variable::TYPE_KEY])) {
      throw new ExerciseConfigException("Exercise variable does not have any type");
    }
    $variable->setType($data[Variable::TYPE_KEY]);

    if (!isset($data[Variable::VALUE_KEY])) {
      throw new ExerciseConfigException("Exercise variable does not have any value");
    }
    $variable->setValue($data[Variable::VALUE_KEY]);

    return $variable;
  }

  /**
   * Builds and checks pipeline configuration from given structured data.
   * @param $data
   * @return Pipeline
   * @throws ExerciseConfigException
   */
  public function loadPipeline($data): Pipeline {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise pipeline is not array");
    }

    $pipeline = new Pipeline();

    if (isset($data[Pipeline::VARIABLES_KEY]) && is_array($data[Pipeline::VARIABLES_KEY])) {
      foreach ($data[Pipeline::VARIABLES_KEY] as $name => $value) {
        $pipeline->addVariable($name, $this->loadVariable($value));
      }
    }

    return $pipeline;
  }

  /**
   * Builds and checks environment configuration from given structured data.
   * @param $data
   * @return Environment
   * @throws ExerciseConfigException
   */
  public function loadEnvironment($data): Environment {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise environment is not array");
    }

    $environment = new Environment();

    if (isset($data[Environment::PIPELINES_KEY]) && is_array($data[Environment::PIPELINES_KEY])) {
      foreach ($data[Environment::PIPELINES_KEY] as $key => $pipeline) {
        $environment->addPipeline($key, $this->loadPipeline($pipeline));
      }
    }

    return $environment;
  }

  /**
   * Builds and checks test configuration from given structured data.
   * @param $data
   * @return Test
   * @throws ExerciseConfigException
   */
  public function loadTest($data): Test {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise test is not array");
    }

    $test = new Test;

    if (!isset($data[Test::PIPELINES_KEY]) || !is_array($data[Test::PIPELINES_KEY])) {
      throw new ExerciseConfigException("Exercise test does not have any defined pipelines");
    }
    foreach ($data[Test::PIPELINES_KEY] as $key => $pipeline) {
      $test->addPipeline($key, $this->loadPipeline($pipeline));
    }

    if (!isset($data[Test::ENVIRONMENTS_KEY]) || !is_array($data[Test::ENVIRONMENTS_KEY])) {
      throw new ExerciseConfigException("Exercise test does not have any defined environments");
    }
    foreach ($data[Test::ENVIRONMENTS_KEY] as $id => $environment) {
      $test->addEnvironment($id, $this->loadEnvironment($environment));
    }

    return $test;
  }

  /**
   * Builds and checks exercise configuration from given structured data.
   * @param $data
   * @return ExerciseConfig
   * @throws ExerciseConfigException
   */
  public function loadExerciseConfig($data): ExerciseConfig {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise configuration is not array");
    }

    $config = new ExerciseConfig;

    if (!isset($data[ExerciseConfig::ENVIRONMENTS_KEY]) || !is_array($data[ExerciseConfig::ENVIRONMENTS_KEY])) {
      throw new ExerciseConfigException("Exercise configuration does not have any environments");
    }
    foreach ($data[ExerciseConfig::ENVIRONMENTS_KEY] as $envId) {
      $config->addEnvironment($envId);
    }

    if (!isset($data[ExerciseConfig::TESTS_KEY]) || !is_array($data[ExerciseConfig::TESTS_KEY])) {
      throw new ExerciseConfigException("Exercise configuration does not have any tests");
    }
    foreach ($data[ExerciseConfig::TESTS_KEY] as $testId => $test) {
      $config->addTest($testId, $this->loadTest($test));
    }

    return $config;
  }

  /**
   * Builds and checks limits from given structured data.
   * @param array $data
   * @param string $boxId Box identifier (name) for better error messages
   * @return Limits
   * @throws ExerciseConfigException
   */
  public function loadLimits($data, $boxId = ""): Limits {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Box '" . $boxId . "': limits are not array");
    }

    $limits = new Limits;

    // *** LOAD OPTIONAL DATAS

    if (isset($data[Limits::WALL_TIME_KEY])) {
      $limits->setWallTime(floatval($data[Limits::WALL_TIME_KEY]));
    }

    if (isset($data[Limits::MEMORY_KEY])) {
      $limits->setMemoryLimit(intval($data[Limits::MEMORY_KEY]));
    }

    if (isset($data[Limits::PARALLEL_KEY])) {
      $limits->setParallel(intval($data[Limits::PARALLEL_KEY]));
    }

    return $limits;
  }

  /**
   * Builds and checks limits wrapper from given data.
   * @param $data
   * @return ExerciseLimits
   * @throws ExerciseConfigException
   */
  public function loadExerciseLimits($data) {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise limits are not array");
    }

    $limits = new ExerciseLimits;

    foreach ($data as $key => $values) {
      $limits->addLimits($key, $this->loadLimits($values, $key));
    }

    return $limits;
  }

}
