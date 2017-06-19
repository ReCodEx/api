<?php

namespace App\Helpers\ExerciseConfig;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration pipeline holder.
 */
class Pipeline implements JsonSerializable {

  /** Name of the variables key */
  const VARIABLES_KEY = "variables";


  /**
   * Variables indexed by name and containing values.
   * @var array
   */
  protected $variables = array();


  /**
   * Get variables for this environment.
   * @return array
   */
  public function getVariables(): array {
    return $this->variables;
  }

  /**
   * Get value of the variable based on given variable name.
   * @param string $key
   * @return null|Variable
   */
  public function getVariable(string $key): ?Variable {
    if (!array_key_exists($key, $this->variables)) {
      return null;
    }

    return $this->variables[$key];
  }

  /**
   * Add variable to this environment.
   * @param string $key
   * @param Variable $value
   * @return $this
   */
  public function addVariable(string $key, Variable $value): Pipeline {
    $this->variables[$key] = $value;
    return $this;
  }

  /**
   * Remove variable based on given variable name.
   * @param string $key
   * @return $this
   */
  public function removeVariable(string $key): Pipeline {
    unset($this->variables[$key]);
    return $this;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];

    $data[self::VARIABLES_KEY] = array();
    foreach ($this->variables as $key => $value) {
      $data[self::VARIABLES_KEY][$key] = $value->toArray();
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
