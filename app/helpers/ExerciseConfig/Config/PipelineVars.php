<?php

namespace App\Helpers\ExerciseConfig;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration exercise config pipeline holder.
 */
class PipelineVars implements JsonSerializable {

  /** Name of the name key */
  const NAME_KEY = "name";
  /** Name of the variables key */
  const VARIABLES_KEY = "variables";


  /**
   * Identifier of pipeline
   * @var string
   */
  protected $name;

  /**
   * Variables table structure.
   * @var VariablesTable
   */
  protected $variablesTable;


  public function __construct() {
    $this->variablesTable = new VariablesTable();
  }


  /**
   * Get identification of pipeline.
   * @return string
   */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * Set identification of pipeline.
   * @param string $name
   * @return PipelineVars
   */
  public function setName(string $name): PipelineVars {
    $this->name = $name;
    return $this;
  }

  /**
   * Get variables for this environment.
   * @return VariablesTable
   */
  public function getVariablesTable(): VariablesTable {
    return $this->variablesTable;
  }

  /**
   * Set variables for this environment.
   * @param VariablesTable $variablesTable
   * @return PipelineVars
   */
  public function setVariablesTable(VariablesTable $variablesTable): PipelineVars {
    $this->variablesTable = $variablesTable;
    return $this;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];
    $data[self::NAME_KEY] = $this->name;
    $data[self::VARIABLES_KEY] = $this->variablesTable->toArray();
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
