<?php

namespace App\Helpers\ExerciseConfig;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration environment holder.
 */
class Environment implements JsonSerializable {

  /** Name of the pipelines key */
  const PIPELINES_KEY = "pipelines";


  /**
   * Array indexed by pipelines name.
   * @var array
   */
  protected $pipelines = array();


  /**
   * Get pipelines for this environment.
   * @return array
   */
  public function getPipelines(): array {
    return $this->pipelines;
  }

  /**
   * Get pipeline for the given name.
   * @param string $name
   * @return PipelineConfig|null
   */
  public function getPipeline(string $name): ?PipelineConfig {
    if (!array_key_exists($name, $this->pipelines)) {
      return null;
    }

    return $this->pipelines[$name];
  }

  /**
   * Add pipeline to this environment.
   * @param string $id
   * @param PipelineConfig $pipeline
   * @return $this
   */
  public function addPipeline(string $id, PipelineConfig $pipeline): Environment {
    $this->pipelines[$id] = $pipeline;
    return $this;
  }

  /**
   * Remove pipeline with given identification.
   * @param string $id
   * @return $this
   */
  public function removePipeline(string $id): Environment {
    unset($this->pipelines[$id]);
    return $this;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];

    $data[self::PIPELINES_KEY] = array();
    foreach ($this->pipelines as $key => $pipeline) {
      $data[self::PIPELINES_KEY][$key] = $pipeline->toArray();
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
