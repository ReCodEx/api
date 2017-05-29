<?php

namespace App\Helpers\ExerciseConfig;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration environment holder.
 */
class Environment implements JsonSerializable {

  /** Name of the test key */
  const PIPELINES_KEY = "pipelines";
  /** Name of the test key */
  const VARIABLES_KEY = "variables";


  /**
   * Array containing identifications of environment pipelines.
   * @var array
   */
  protected $pipelines = array();

  /**
   * Variables indexed by name and containing values.
   * @var array
   */
  protected $variables = array();


  /**
   * Get pipelines for this environment.
   * @return array
   */
  public function getPipelines(): array {
    return $this->pipelines;
  }

  /**
   * Add pipeline to this environment.
   * @param string $pipeline
   * @return $this
   */
  public function addPipeline(string $pipeline): Environment {
    $this->pipelines[] = $pipeline;
    return $this;
  }

  /**
   * Get variables for this environment.
   * @return array
   */
  public function getVariables(): array {
    return $this->variables;
  }

  /**
   * Add ariable to this environment.
   * @param string $key
   * @param string $value
   * @return $this
   */
  public function addVariable(string $key, string $value): Environment {
    $this->variables[$key] = $value;
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
