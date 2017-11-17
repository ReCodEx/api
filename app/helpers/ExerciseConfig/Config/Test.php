<?php

namespace App\Helpers\ExerciseConfig;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration test holder.
 */
class Test implements JsonSerializable {

  /** Name of the environments key */
  const ENVIRONMENTS_KEY = "environments";

  /**
   * Array of environments with their specific settings.
   * @var array
   */
  protected $environments = array();


  /**
   * Get associative array of environments.
   * @return Environment[]
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
