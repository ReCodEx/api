<?php

namespace App\Helpers\ExerciseConfig;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration test holder.
 */
class Test implements JsonSerializable {

  /** Name of the test key */
  const PIPELINES_KEY = "pipelines";
  /** Name of the test key */
  const VARIABLES_KEY = "variables";
  /** Name of the test key */
  const ENVIRONMENTS_KEY = "environments";

  /**
   * Array containing identifications of default pipelines.
   * @var array
   */
  protected $pipelines = array();

  /**
   * Default variables indexed by name and containing values.
   * @var array
   */
  protected $variables = array();

  /**
   * Array of environments with their specific settings.
   * @var array
   */
  protected $environments = array();


  /**
   * Get default pipelines for this test.
   * @return array
   */
  public function getPipelines(): array {
    return $this->pipelines;
  }

  /**
   * Add default pipeline to this test.
   * @param string $pipeline
   * @return $this
   */
  public function addPipeline(string $pipeline): Test {
    $this->pipelines[] = $pipeline;
    return $this;
  }

  /**
   * Get default variables for this test.
   * @return array
   */
  public function getVariables(): array {
    return $this->variables;
  }

  /**
   * Add default variable to this test.
   * @param string $key
   * @param string $value
   * @return $this
   */
  public function addVariable(string $key, string $value): Test {
    $this->variables[$key] = $value;
    return $this;
  }

  /**
   * Remove variable based on given variable name.
   * @param string $key
   * @return $this
   */
  public function removeVariable(string $key): Test {
    unset($this->variables[$key]);
    return $this;
  }

  /**
   * Get value of the variable based on given variable name.
   * @param string $key
   * @return string|null
   */
  public function getVariableValue(string $key): ?string {
    if (!array_key_exists($key, $this->variables)) {
      return null;
    }

    return $this->variables[$key];
  }

  /**
   * Get associative array of environments.
   * @return array
   */
  public function getEnvironments(): array {
    return $this->environments;
  }

  /**
   * Get environment for the given name.
   * @param string $name
   * @return Environment|null
   */
  public function getEnvironment(string $name): ?Environment {
    if (!array_key_exists($name, $this->environments)) {
      return null;
    }

    return $this->environments[$name];
  }

  /**
   * Add environment into this holder.
   * @param string $id environment identification
   * @param Environment $environment
   * @return $this|Test
   */
  public function addEnvironment(string $id, Environment $environment): Test {
    $this->environments[$id] = $environment;
    return $this;
  }

  /**
   * Remove environment with given identification.
   * @param string $id
   * @return $this
   */
  public function removeEnvironment(string $id): Test {
    unset($this->environments[$id]);
    return $this;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];

    $data[self::PIPELINES_KEY] = array();
    foreach ($this->pipelines as $pipeline) {
      $data[self::PIPELINES_KEY][] = $pipeline;
    }
    $data[self::VARIABLES_KEY] = array();
    foreach ($this->variables as $key => $value) {
      $data[self::VARIABLES_KEY][$key] = $value;
    }
    $data[self::ENVIRONMENTS_KEY] = array();
    foreach ($this->environments as $key => $environment) {
      $data[self::ENVIRONMENTS_KEY][$key] = $environment->toArray();
    }

    return $data;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

  /**
   * Enable automatic serialization to JSON
   * @return array
   */
  public function jsonSerialize() {
    return $this->toArray();
  }
}
