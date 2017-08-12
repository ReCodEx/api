<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Ports;


use App\Helpers\ExerciseConfig\Variable;
use JsonSerializable;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class for ports.
 */
abstract class Port implements JsonSerializable
{
  /**
   * Meta information about port.
   * @var PortMeta
   */
  protected $meta;

  /**
   * Actual reference to the variable value.
   * @note Used during compilation of configuration, has to be set before usage.
   * @var Variable
   */
  protected $variableValue = null;

  /**
   * Port constructor.
   * @param PortMeta $meta
   */
  public function __construct(PortMeta $meta) {
    $this->meta = $meta;
    $this->meta->setType($this->getType());
  }

  /**
   * Get name of this port.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->meta->getName();
  }

  /**
   * Get type of this port.
   * @return null|string
   */
  public abstract function getType(): ?string;

  /**
   * Get variable value of the port.
   * @return null|string
   */
  public function getVariable(): ?string {
    return $this->meta->getVariable();
  }

  /**
   * Get variable value.
   * @return Variable|null
   */
  public function getVariableValue(): ?Variable {
    return $this->variableValue;
  }

  /**
   * Set variable value.
   * @param Variable $variableValue
   */
  public function setVariableValue(Variable $variableValue) {
    $this->variableValue = $variableValue;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    return $this->meta->toArray();
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
