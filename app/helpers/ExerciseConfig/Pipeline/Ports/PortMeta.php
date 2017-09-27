<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Ports;

use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration port holder.
 */
class PortMeta implements JsonSerializable {

  /** Name of the type key */
  const TYPE_KEY = "type";
  /** Name of the value key */
  const VARIABLE_KEY = "value";


  /**
   * Port identification.
   * @var string
   */
  protected $name = null;

  /**
   * Type of this port.
   * @var string
   */
  protected $type = null;

  /**
   * Bound variable for this port.
   * @var string
   */
  protected $variable = "";

  /**
   * Create instance of PortMeta with given values.
   * @param string $name
   * @param string $type
   * @param null|string $variable
   * @return PortMeta
   */
  public static function create(string $name, string $type, string $variable = ""): PortMeta {
    $result = new static();
    $result->setName($name);
    $result->setType($type);
    $result->setVariable($variable);
    return $result;
  }

  /**
 * Get name of this port.
 * @return null|string
 */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * Set name of this port.
   * @param string $name
   * @return PortMeta
   */
  public function setName(string $name): PortMeta {
    $this->name = $name;
    return $this;
  }

  /**
   * Get type of this port.
   * @return null|string
   */
  public function getType(): ?string {
    return $this->type;
  }

  /**
   * Set type of this port.
   * @param string $type
   * @return PortMeta
   */
  public function setType(string $type): PortMeta {
    $this->type = $type;
    return $this;
  }

  /**
   * Get variable bounded to this port.
   * @return string
   */
  public function getVariable(): string {
    return $this->variable;
  }

  /**
   * Set variable bounded to this port.
   * @param string $variable
   * @return PortMeta
   */
  public function setVariable(string $variable): PortMeta {
    $this->variable = $variable;
    return $this;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];

    $data[self::TYPE_KEY] = $this->type;
    $data[self::VARIABLE_KEY] = $this->variable;

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
