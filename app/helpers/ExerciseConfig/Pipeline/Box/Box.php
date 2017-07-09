<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use JsonSerializable;
use Symfony\Component\Yaml\Yaml;


/**
 * Abstract base class for all boxes which contains only basic meta information
 * about box.
 */
abstract class Box implements JsonSerializable
{
  /**
   * Meta information about this box.
   * @var BoxMeta
   */
  protected $meta;

  /**
   * Box constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    $this->meta = $meta;
  }

  /**
   * Get name of this box.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->meta->getName();
  }

  /**
   * Get input ports of this box.
   * @return array
   */
  public function getInputPorts(): array {
    return $this->meta->getInputPorts();
  }

  /**
   * Get output ports of this box.
   * @return array
   */
  public function getOutputPorts(): array {
    return $this->meta->getOutputPorts();
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
