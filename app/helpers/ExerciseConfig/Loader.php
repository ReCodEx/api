<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;

/**
 * Loader service which is able to load exercise configuration into internal
 * holders. Given data are checked against mandatory fields and in case of error
 * exception is thrown.
 */
class Loader {

  /**
   * @var BoxService
   */
  private $boxService;

  /**
   * Loader constructor.
   * @param BoxService $boxService
   */
  public function __construct(BoxService $boxService) {
    $this->boxService = $boxService;
  }

  /**
   * Builds and checks variable configuration from given structured data.
   * @param $data
   * @return Variable
   * @throws ExerciseConfigException
   */
  public function loadVariable($data): Variable {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise variable is not array");
    }

    if (!isset($data[Variable::TYPE_KEY]) || !is_scalar($data[Variable::TYPE_KEY])) {
      throw new ExerciseConfigException("Exercise variable does not have any type");
    }
    $variable = new Variable($data[Variable::TYPE_KEY]);

    if (!isset($data[Variable::NAME_KEY]) || !is_scalar($data[Variable::NAME_KEY])) {
      throw new ExerciseConfigException("Exercise variable does not have a name");
    }
    $variable->setName($data[Variable::NAME_KEY]);

    if (isset($data[Variable::VALUE_KEY])) {
      $variable->setValue($data[Variable::VALUE_KEY]);
    }

    return $variable;
  }

  /**
   * Builds and checks variables table configuration from given structured data.
   * @param $data
   * @return VariablesTable
   * @throws ExerciseConfigException
   */
  public function loadVariablesTable($data): VariablesTable {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise variable is not array");
    }

    $table = new VariablesTable;

    foreach ($data as $value) {
      $table->set($this->loadVariable($value));
    }

    return $table;
  }

  /**
   * Builds and checks pipeline variables configuration from given structured data.
   * @param $data
   * @return PipelineVars
   * @throws ExerciseConfigException
   */
  public function loadPipelineVars($data): PipelineVars {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise pipeline is not array");
    }

    $pipeline = new PipelineVars();

    if (!isset($data[PipelineVars::NAME_KEY])) {
      throw new ExerciseConfigException("Exercise pipeline does not have name specified");
    }
    $pipeline->setName($data[PipelineVars::NAME_KEY]);

    if (isset($data[PipelineVars::VARIABLES_KEY]) && is_array($data[PipelineVars::VARIABLES_KEY])) {
      $pipeline->setVariablesTable($this->loadVariablesTable($data[PipelineVars::VARIABLES_KEY]));
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
      foreach ($data[Environment::PIPELINES_KEY] as $pipeline) {
        $environment->addPipeline($this->loadPipelineVars($pipeline));
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
   * @param string $testId Test identifier (name) for better error messages
   * @return Limits
   * @throws ExerciseConfigException
   */
  public function loadLimits($data, $testId = ""): Limits {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Test '" . $testId . "': limits are not array");
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
  public function loadExerciseLimits($data): ExerciseLimits {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Exercise limits are not array");
    }

    $limits = new ExerciseLimits;

    foreach ($data as $testId => $testVal) {
      $limits->addLimits($testId, $this->loadLimits($testVal, $testId));
    }

    return $limits;
  }

  /**
   * Builds and checks port configuration from given structured data.
   * @param string $name
   * @param $data
   * @return Port
   * @throws ExerciseConfigException
   */
  public function loadPort(string $name, $data): Port {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Pipeline port is not array");
    }

    $port = new PortMeta;
    $port->setName($name);

    if (!isset($data[PortMeta::TYPE_KEY])) {
      throw new ExerciseConfigException("Pipeline port '$name' does not have any type");
    }
    $port->setType($data[PortMeta::TYPE_KEY]);

    if (isset($data[PortMeta::VARIABLE_KEY])) {
      $port->setVariable($data[PortMeta::VARIABLE_KEY]);
    }

    return new Port($port);
  }

  /**
   * Builds and checks box structure from given data.
   * @param $data
   * @return Box
   * @throws ExerciseConfigException
   */
  public function loadBox($data): Box {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Box is not array");
    }

    $boxMeta = new BoxMeta;

    if (!isset($data[BoxMeta::NAME_KEY])) {
      throw new ExerciseConfigException("Box metadatas do not have name specified");
    }
    $boxMeta->setName($data[BoxMeta::NAME_KEY]);

    if (!isset($data[BoxMeta::TYPE_KEY])) {
      throw new ExerciseConfigException("Box metadatas do not have type specified");
    }
    $boxMeta->setType($data[BoxMeta::TYPE_KEY]);

    if (!isset($data[BoxMeta::PORTS_IN_KEY]) || !is_array($data[BoxMeta::PORTS_IN_KEY])) {
      $data[BoxMeta::PORTS_IN_KEY] = [];
    }
    foreach ($data[BoxMeta::PORTS_IN_KEY] as $name => $portData) {
      $port = $this->loadPort($name, $portData);
      $boxMeta->addInputPort($port);
    }

    if (!isset($data[BoxMeta::PORTS_OUT_KEY]) || !is_array($data[BoxMeta::PORTS_OUT_KEY])) {
      $data[BoxMeta::PORTS_OUT_KEY] = [];
    }
    foreach ($data[BoxMeta::PORTS_OUT_KEY] as $name => $portData) {
      $port = $this->loadPort($name, $portData);
      $boxMeta->addOutputPort($port);
    }

    return $this->boxService->create($boxMeta);
  }

  /**
   * Builds and checks pipeline wrapper from given data.
   * @param $data
   * @return Pipeline
   * @throws ExerciseConfigException
   */
  public function loadPipeline($data): Pipeline {
    if (!is_array($data)) {
      throw new ExerciseConfigException("Pipeline is not array");
    }

    $pipeline = new Pipeline;

    if (isset($data[Pipeline::VARIABLES_KEY]) && is_array($data[Pipeline::VARIABLES_KEY])) {
      $pipeline->setVariablesTable($this->loadVariablesTable($data[Pipeline::VARIABLES_KEY]));
    }

    if (isset($data[Pipeline::BOXES_KEY]) && is_array($data[Pipeline::BOXES_KEY])) {
      foreach ($data[Pipeline::BOXES_KEY] as $box) {
        $pipeline->set($this->loadBox($box));
      }
    }

    return $pipeline;
  }

}
