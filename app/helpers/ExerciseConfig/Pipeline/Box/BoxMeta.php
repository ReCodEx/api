<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration box holder.
 */
class BoxMeta implements JsonSerializable {

  /** Name of the name key */
  const NAME_KEY = "name";
  /** Name of the ports in key */
  const PORTS_IN_KEY = "portsIn";
  /** Name of the ports out key */
  const PORTS_OUT_KEY = "portsOut";
  /** Name of the type key */
  const TYPE_KEY = "type";


  /**
   * Box name.
   * @var string
   */
  protected $name = null;

  /**
   * Box type.
   * @var string
   */
  protected $type = null;

  /**
   * Ports in for this box.
   * @var array
   */
  protected $portsIn = array();

  /**
   * Ports out for this box.
   * @var array
   */
  protected $portsOut = array();


  /**
   * Get name of this box.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * Set name of this box.
   * @param string $name
   * @return BoxMeta
   */
  public function setName(string $name): BoxMeta {
    $this->name = $name;
    return $this;
  }

  /**
   * Get type of this box.
   * @return null|string
   */
  public function getType(): ?string {
    return $this->type;
  }

  /**
   * Set type of this box.
   * @param string $type
   * @return BoxMeta
   */
  public function setType(string $type): BoxMeta {
    $this->type = $type;
    return $this;
  }

  /**
   * Get input ports.
   * @return array
   */
  public function getInputPorts(): array {
    return $this->portsIn;
  }

  /**
   * Set input ports.
   * @param array $ports
   * @return BoxMeta
   */
  public function setInputPorts(array $ports): BoxMeta {
    $this->portsIn = array();
    foreach ($ports as $port) {
      $this->portsIn[$port->getName()] = $port;
    }
    return $this;
  }

  /**
   * Get input port by given index.
   * @param string $key
   * @return Port|null
   */
  public function getInputPort(string $key): ?Port {
    if (array_key_exists($key, $this->portsIn)) {
      return $this->portsIn[$key];
    }
    return null;
  }

  /**
   * Add input port.
   * @param Port $port
   * @return BoxMeta
   */
  public function addInputPort(Port $port): BoxMeta {
    $this->portsIn[$port->getName()] = $port;
    return $this;
  }

  /**
   * Remove input port with given key.
   * @param string $key
   * @return BoxMeta
   */
  public function removeInputPort(string $key): BoxMeta {
    unset($this->portsIn[$key]);
    return $this;
  }

  /**
   * Get output ports.
   * @return array
   */
  public function getOutputPorts(): array {
    return $this->portsOut;
  }

  /**
   * Set output ports.
   * @param array $ports
   * @return BoxMeta
   */
  public function setOutputPorts(array $ports): BoxMeta {
    $this->portsOut = array();
    foreach ($ports as $port) {
      $this->portsOut[$port->getName()] = $port;
    }
    return $this;
  }

  /**
   * Get output port by given index.
   * @param string $key
   * @return Port|null
   */
  public function getOutputPort(string $key): ?Port {
    if (array_key_exists($key, $this->portsOut)) {
      return $this->portsOut[$key];
    }
    return null;
  }

  /**
   * Add output port.
   * @param Port $port
   * @return BoxMeta
   */
  public function addOutputPort(Port $port): BoxMeta {
    $this->portsOut[$port->getName()] = $port;
    return $this;
  }

  /**
   * Remove output port with given key.
   * @param string $key
   * @return BoxMeta
   */
  public function removeOutputPort(string $key): BoxMeta {
    unset($this->portsOut[$key]);
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

    $data[self::PORTS_IN_KEY] = array();
    foreach ($this->portsIn as $port) {
      $data[self::PORTS_IN_KEY][$port->getName()] = $port->getVariable();
    }

    $data[self::PORTS_OUT_KEY] = array();
    foreach ($this->portsOut as $port) {
      $data[self::PORTS_OUT_KEY][$port->getName()] = $port->getVariable();
    }

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
