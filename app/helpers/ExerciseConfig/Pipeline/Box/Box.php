<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
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
   * Implementation should check loaded metadatas which for now should include
   * only validation of ports. Called after construction in Box factory.
   * @return mixed
   * @throws ExerciseConfigException
   */
  public abstract function validateMetadata();

  /**
   * When listing default boxes which are available, there has to be somehow
   * filled default values, like names of the ports and values. To enforce Box
   * authors to make default values, fillDefaults abstract function
   * was introduced.
   * @return mixed
   */
  public abstract function fillDefaults();


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
