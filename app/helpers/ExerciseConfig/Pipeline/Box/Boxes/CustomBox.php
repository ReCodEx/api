<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Customizable box whose ports can be modified. Default values for ports and
 * name are not implemented.
 * @note Should be used only for internal purposes.
 */
class CustomBox extends Box
{
  /** Type key */
  public static $CUSTOM_BOX_TYPE = "custom";

  /**
   * CustomBox constructor.
   * @param string $name
   */
  public function __construct(string $name = "") {
    parent::__construct((new BoxMeta)->setName($name));
  }


  /**
   * Set name of box.
   * @param string $name
   * @return CustomBox
   */
  public function setName(string $name): CustomBox {
    $this->meta->setName($name);
    return $this;
  }

  /**
   * Add input port of this box.
   * @param Port $port
   * @return CustomBox
   */
  public function addInputPort(Port $port): CustomBox {
    $this->meta->addInputPort($port);
    return $this;
  }

  /**
   * Clear input ports of this box.
   * @return CustomBox
   */
  public function clearInputPorts(): CustomBox {
    $this->meta->setInputPorts(array());
    return $this;
  }

  /**
   * Add output port of this box.
   * @param Port $port
   * @return CustomBox
   */
  public function addOutputPort(Port $port): CustomBox {
    $this->meta->addOutputPort($port);
    return $this;
  }

  /**
   * Clear output ports of this box.
   * @return CustomBox
   */
  public function clearOutputPorts(): CustomBox {
    $this->meta->setOutputPorts(array());
    return $this;
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$CUSTOM_BOX_TYPE;
  }

  /**
   * Get default input ports for this box.
   * @return array
   */
  public function getDefaultInputPorts(): array {
    return array();
  }

  /**
   * Get default output ports for this box.
   * @return array
   */
  public function getDefaultOutputPorts(): array {
    return array();
  }

  /**
   * Get default name of this box.
   * @return string
   */
  public function getDefaultName(): string {
    return "";
  }

  /**
   * Compile box into set of low-level tasks.
   * @return Task[]
   */
  public function compile(): array {
    $task = new Task();
    return [$task];
  }

}
