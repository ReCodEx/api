<?php

namespace App\Helpers\ExerciseConfig;


use JsonSerializable;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class for variables.
 */
abstract class Variable implements JsonSerializable
{
  /**
   * Meta information about variable.
   * @var VariableMeta
   */
  protected $meta;

  /**
   * Variable constructor.
   * @param VariableMeta $meta
   */
  public function __construct(VariableMeta $meta) {
    $this->meta = $meta;
  }

  /**
   * Get type of this variable.
   * @return null|string
   */
  public abstract function getType(): ?string;

  /**
   * Get name of the variable.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->meta->getName();
  }

  /**
   * Get value of the variable.
   * @return null|string
   */
  public function getValue(): ?string {
    return $this->meta->getValue();
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
