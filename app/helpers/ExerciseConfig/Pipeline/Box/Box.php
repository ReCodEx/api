<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use JsonSerializable;
use Symfony\Component\Yaml\Yaml;


/**
 * Abstract base class for all boxes which contains only basic meta information
 * about box. Defines constructor with one parameter which has to be called
 * from children one, that means children have to have constructor with the same
 * parameter in order to be constructed properly from BoxService. Also note that
 * BoxMeta information can be passed empty and after that fillDefaults function
 * called, this means validation of box metadata cannot be executed within
 * child constructor. Calling of validateMetadata is handled in BoxService,
 * which is the only proper way how to construct correct Box.
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
   * Get default input ports of some particular box.
   * Should be static property which is present only once for instance.
   */
  public abstract function getDefaultInputPorts(): array;

  /**
   * Get default output ports of some particular box.
   * Should be static property which is present only once for instance.
   */
  public abstract function getDefaultOutputPorts(): array;


  /**
   * When listing default boxes which are available, there has to be somehow
   * filled default values, like names of the ports and values.
   */
  public function fillDefaults() {
    $this->meta->setInputPorts($this->getDefaultInputPorts());
    $this->meta->setOutputPorts($this->getDefaultOutputPorts());
  }

  /**
   * Check loaded metadatas which for now should include only validation of
   * ports. Called after construction in Box factory.
   * @throws ExerciseConfigException
   */
  public function validateMetadata() {
    $defaultInPorts = $this->getDefaultInputPorts();
    $defaultOutPorts = $this->getDefaultOutputPorts();

    // different number of input ports
    if (count($defaultInPorts) !== count($this->getInputPorts())) {
      throw new ExerciseConfigException("Number of input ports is not the same");
    }

    // different number of output ports
    if (count($defaultOutPorts) !== count($this->getOutputPorts())) {
      throw new ExerciseConfigException("Number of output ports is not the same");
    }

    // check if all default input ports are present
    foreach ($defaultInPorts as $defaultInPort) {
      $inPort = $this->meta->getInputPort($defaultInPort->getName());
      if (!$inPort) {
        throw new ExerciseConfigException("Default input port not found");
      }
    }

    // check if all default output ports are present
    foreach ($defaultOutPorts as $defaultOutPort) {
      $outPort = $this->meta->getOutputPort($defaultOutPort->getName());
      if (!$outPort) {
        throw new ExerciseConfigException("Default output port not found");
      }
    }
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
