<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\UndefinedPort;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\Tasks\Task;
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
    $this->meta->setType($this->getType());
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
   * Get default name of some particular box.
   * Should be static property which is present only once for instance.
   */
  public abstract function getDefaultName(): string;

  /**
   * Get type of this box.
   * @return string
   */
  public abstract function getType(): string;

  /**
   * Compile box into set of low-level tasks.
   * @return Task[]
   */
  public abstract function compile(): array;


  /**
   * When listing default boxes which are available, there has to be somehow
   * filled default values, like names of the ports and values.
   */
  public function fillDefaults() {
    $this->meta->setName($this->getDefaultName());
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
      throw new ExerciseConfigException("Number of input ports is not the same in box '{$this->getName()}'");
    }

    // different number of output ports
    if (count($defaultOutPorts) !== count($this->getOutputPorts())) {
      throw new ExerciseConfigException("Number of output ports is not the same in box '{$this->getName()}'");
    }

    // check if all default input ports are present and have same type
    foreach ($defaultInPorts as $defaultInPort) {
      $inPort = $this->meta->getInputPort($defaultInPort->getName());
      if (!$inPort || (!($inPort->getType() === $defaultInPort->getType())) &&
          !($defaultInPort->getType() === VariableTypes::$UNDEFINED_TYPE)) {
        // input port is missing or types of port and default port are not the
        // same, but if types are not the same and default port is undefined
        // there can be any type in the input port
        throw new ExerciseConfigException("Default input port '{$defaultInPort->getName()}' missing or malformed in box '{$this->getName()}'");
      }
    }

    // check if all default output ports are present and have same type
    foreach ($defaultOutPorts as $defaultOutPort) {
      $outPort = $this->meta->getOutputPort($defaultOutPort->getName());
      if (!$outPort || (!($outPort->getType() === $defaultOutPort->getType())) &&
          !($defaultOutPort->getType() === VariableTypes::$UNDEFINED_TYPE)) {
        // output port is missing or types of port and default port are not the
        // same, but if types are not the same and default port is undefined
        // there can be any type in the output port
        throw new ExerciseConfigException("Default output port '{$defaultOutPort->getName()}' missing or malformed in box '{$this->getName()}'");
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
   * Get all ports of the box.
   * @return Port[]
   */
  public function getPorts(): array {
    return array_merge($this->meta->getInputPorts(), $this->meta->getOutputPorts());
  }

  /**
   * Get input ports of this box.
   * @return Port[]
   */
  public function getInputPorts(): array {
    return $this->meta->getInputPorts();
  }

  /**
   * Get input port of given name from this box.
   * @param string $port
   * @return Port|null
   */
  public function getInputPort(string $port): ?Port {
    return $this->meta->getInputPort($port);
  }

  /**
   * Get output ports of this box.
   * @return Port[]
   */
  public function getOutputPorts(): array {
    return $this->meta->getOutputPorts();
  }

  /**
   * Get output port of given name from this box.
   * @param string $port
   * @return Port|null
   */
  public function getOutputPort(string $port): ?Port {
    return $this->meta->getOutputPort($port);
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
