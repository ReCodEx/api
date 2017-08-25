<?php

namespace App\Helpers\ExerciseConfig;

use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration variable holder.
 */
class VariableMeta implements JsonSerializable {

  /** Name of the name key */
  const NAME_KEY = "name";
  /** Name of the type key */
  const TYPE_KEY = "type";
  /** Name of the value key */
  const VALUE_KEY = "value";


  /**
   * Variable name.
   * @var string
   */
  protected $name = null;

  /**
   * Variable type.
   * @var string
   */
  protected $type = null;

  /**
   * Variable value.
   * @var string|array
   */
  protected $value = null;


  /**
   * Get name of this variable.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * Set name of this variable.
   * @param string $name
   * @return VariableMeta
   */
  public function setName(string $name): VariableMeta {
    $this->name = $name;
    return $this;
  }

  /**
   * Get type of this variable.
   * @return null|string
   */
  public function getType(): ?string {
    return $this->type;
  }

  /**
   * Set type of this variable.
   * @param string $type
   * @return VariableMeta
   */
  public function setType(string $type): VariableMeta {
    $this->type = $type;
    return $this;
  }

  /**
   * Get value of this variable.
   * @return array|string
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * Set value of this variable.
   * @param array|string $value
   * @return VariableMeta
   */
  public function setValue($value): VariableMeta {
    $this->value = $value;
    return $this;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];

    $data[self::NAME_KEY] = $this->name;
    $data[self::TYPE_KEY] = $this->type;
    $data[self::VALUE_KEY] = $this->value;

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
