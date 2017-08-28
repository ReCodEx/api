<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Ports;


use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use JsonSerializable;
use Symfony\Component\Yaml\Yaml;

/**
 * Port structure which contains type information and possibly reference to
 * variable.
 */
class Port implements JsonSerializable
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
    $this->validateType();
  }

  /**
   * Validate type given during construction and set appropriate attributes.
   * @throws ExerciseConfigException
   */
  private function validateType() {
    if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$FILE_ARRAY_TYPE)) {
      $this->meta->setType(VariableTypes::$FILE_ARRAY_TYPE);
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$FILE_TYPE)) {
      $this->meta->setType(VariableTypes::$FILE_TYPE);
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$STRING_ARRAY_TYPE)) {
      $this->meta->setType(VariableTypes::$STRING_ARRAY_TYPE);
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$STRING_TYPE)) {
      $this->meta->setType(VariableTypes::$STRING_TYPE);
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$UNDEFINED_TYPE)) {
      $this->meta->setType(VariableTypes::$UNDEFINED_TYPE);
    } else {
      throw new ExerciseConfigException("Unknown port type: {$this->meta->getType()}");
    }
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
  public function getType(): ?string {
    return $this->meta->getType();
  }

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
