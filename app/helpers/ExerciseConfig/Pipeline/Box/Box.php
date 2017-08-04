<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\UndefinedPort;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\VariablesTable;
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
   * Pipeline variables from exercise configuration which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $exerciseConfigVariables;

  /**
   * Variables from environment configuration which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $environmentConfigVariables;

  /**
   * Variables from pipeline to which this box belong to, which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $pipelineVariables;

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
   * Get default name of some particular box.
   * Should be static property which is present only once for instance.
   */
  public abstract function getDefaultName(): string;


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
      throw new ExerciseConfigException("Number of input ports is not the same");
    }

    // different number of output ports
    if (count($defaultOutPorts) !== count($this->getOutputPorts())) {
      throw new ExerciseConfigException("Number of output ports is not the same");
    }

    // check if all default input ports are present and have same type
    foreach ($defaultInPorts as $defaultInPort) {
      $defaultPortType = get_class($defaultInPort);
      $inPort = $this->meta->getInputPort($defaultInPort->getName());
      if (!$inPort || (!($inPort instanceof $defaultPortType)) && !($defaultInPort instanceof UndefinedPort)) {
        throw new ExerciseConfigException("Default input port '{$defaultInPort->getName()}' missing or malformed");
      }
    }

    // check if all default output ports are present and have same type
    foreach ($defaultOutPorts as $defaultOutPort) {
      $defaultPortType = get_class($defaultOutPort);
      $outPort = $this->meta->getOutputPort($defaultOutPort->getName());
      if (!$outPort || (!($outPort instanceof $defaultPortType)) && !($defaultOutPort instanceof UndefinedPort)) {
        throw new ExerciseConfigException("Default output port '{$defaultOutPort->getName()}' missing or malformed");
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
   * @return Port[]
   */
  public function getInputPorts(): array {
    return $this->meta->getInputPorts();
  }

  /**
   * Get output ports of this box.
   * @return Port[]
   */
  public function getOutputPorts(): array {
    return $this->meta->getOutputPorts();
  }

  /**
   * Get pipeline variables from exercise configuration.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getExerciseConfigVariables(): ?VariablesTable {
    return $this->exerciseConfigVariables;
  }

  /**
   * Set pipeline variables from exercise configuration.
   * @param VariablesTable $variablesTable
   * @return Box
   */
  public function setExerciseConfigVariables(VariablesTable $variablesTable): Box {
    $this->exerciseConfigVariables = $variablesTable;
    return $this;
  }

  /**
   * Get variables from environment configuration.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getEnvironmentConfigVariables(): ?VariablesTable {
    return $this->environmentConfigVariables;
  }

  /**
   * Set variables from environment configuration.
   * @param VariablesTable $variablesTable
   * @return Box
   */
  public function setEnvironmentConfigVariables(VariablesTable $variablesTable): Box {
    $this->environmentConfigVariables = $variablesTable;
    return $this;
  }

  /**
   * Get variables from pipeline.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getPipelineVariables(): ?VariablesTable {
    return $this->pipelineVariables;
  }

  /**
   * Set variables from pipeline.
   * @param VariablesTable $variablesTable
   * @return Box
   */
  public function setPipelineVariables(VariablesTable $variablesTable): Box {
    $this->pipelineVariables = $variablesTable;
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
