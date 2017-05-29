<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;

/**
 * Loader service which is able to load exercise configuration into internal
 * holders. Given data are checked against mandatory fields and in case of error
 * exception is thrown.
 */
class Loader {

  public function loadEnvironment($data): Environment {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise environment is not array");
    }

    $environment = new Environment();

    if (isset($data[Environment::PIPELINES_KEY]) && is_array($data[Environment::PIPELINES_KEY])) {
      foreach ($data[Environment::PIPELINES_KEY] as $pipeline) {
        $environment->addPipeline($pipeline);
      }
    }

    if (isset($data[Environment::VARIABLES_KEY]) && is_array($data[Environment::VARIABLES_KEY])) {
      foreach ($data[Environment::VARIABLES_KEY] as $name => $value) {
        $environment->addVariable($name, $value);
      }
    }

    return $environment;
  }

  public function loadTest($data): Test {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise test is not array");
    }

    $test = new Test;

    if (!isset($data[Test::NAME_KEY])) {
      throw new ExerciseConfigException("Exercise test has no name");
    }
    $test->setName(Test::NAME_KEY);

    if (!isset($data[Test::PIPELINES_KEY]) || !is_array($data[Test::PIPELINES_KEY])) {
      throw new ExerciseConfigException("Exercise test does not have any defined pipelines");
    }
    foreach ($data[Test::PIPELINES_KEY] as $pipeline) {
      $test->addPipeline($pipeline);
    }

    if (isset($data[Test::VARIABLES_KEY]) && is_array($data[Test::VARIABLES_KEY])) {
      throw new ExerciseConfigException("Exercise test does not have any defined variables");
    }
    foreach ($data[Test::VARIABLES_KEY] as $name => $value) {
      $test->addVariable($name, $value);
    }

    if (isset($data[Test::ENVIRONMENTS_KEY]) && is_array($data[Test::ENVIRONMENTS_KEY])) {
      throw new ExerciseConfigException("Exercise test does not have any defined environments");
    }
    foreach ($data[Test::ENVIRONMENTS_KEY] as $id => $environment) {
      $test->addEnvironment($id, $this->loadEnvironment($environment));
    }

    return $test;
  }

  public function loadExerciseConfig($data): ExerciseConfig {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise configuration is not array");
    }

    $config = new ExerciseConfig;

    foreach ($data as $test) {
      $config->addTest($this->loadTest($test));
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
