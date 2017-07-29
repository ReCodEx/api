<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Ports;


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
   * Port constructor.
   * @param PortMeta $meta
   */
  public function __construct(PortMeta $meta) {
    $this->meta = $meta;
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
