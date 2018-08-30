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
   * Determines if variable is array or not.
   * @var bool
   */
  protected $isArray = false;

  /**
   * Actual reference to the variable value.
   * @note Used during compilation of configuration, has to be set before usage.
   * @var Variable
   */
  protected $variableValue = null;

  /**
   * Port constructor.
   * @param PortMeta $meta
   * @throws ExerciseConfigException
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
      $this->isArray = true;
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$FILE_TYPE)) {
      $this->meta->setType(VariableTypes::$FILE_TYPE);
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$REMOTE_FILE_ARRAY_TYPE)) {
      $this->meta->setType(VariableTypes::$REMOTE_FILE_ARRAY_TYPE);
      $this->isArray = true;
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$REMOTE_FILE_TYPE)) {
      $this->meta->setType(VariableTypes::$REMOTE_FILE_TYPE);
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$STRING_ARRAY_TYPE)) {
      $this->meta->setType(VariableTypes::$STRING_ARRAY_TYPE);
      $this->isArray = true;
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$STRING_TYPE)) {
      $this->meta->setType(VariableTypes::$STRING_TYPE);
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
   * True if port can hold an array.
   * @return bool
   */
  public function isArray(): bool {
    return $this->isArray;
  }

  /**
   * True if port is of file type.
   * @return bool
   */
  public function isFile(): bool {
    return $this->meta->getType() === VariableTypes::$FILE_TYPE ||
      $this->meta->getType() === VariableTypes::$FILE_ARRAY_TYPE;
  }

  /**
   * Get variable value.
   * @return Variable|null
   */
  public function getVariableValue(): ?Variable {
    return $this->variableValue;
  }

  /**
   * Clone and set variable value.
   * @param Variable $variableValue
   * @return Port
   */
  public function setVariableValue(Variable $variableValue): Port {
    $this->variableValue = $variableValue;
    return $this;
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
