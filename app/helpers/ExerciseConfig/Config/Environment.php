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
   * @var PipelineVars[]
   */
  protected $pipelines = array();


  /**
   * Get pipelines for this environment.
   * @return PipelineVars[]
   */
  public function getPipelines(): array {
    return $this->pipelines;
  }

  /**
   * Get pipeline for the given name.
   * @param string $name
   * @return PipelineVars|null
   */
  public function getPipeline(string $name): ?PipelineVars {
    if (!array_key_exists($name, $this->pipelines)) {
      return null;
    }

    return $this->pipelines[$name];
  }

  /**
   * Add pipeline to this environment.
   * @param PipelineVars $pipeline
   * @return $this
   */
  public function addPipeline(PipelineVars $pipeline): Environment {
    $this->pipelines[$pipeline->getId()] = $pipeline;
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
    foreach ($this->pipelines as $pipeline) {
      $data[self::PIPELINES_KEY][] = $pipeline->toArray();
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
