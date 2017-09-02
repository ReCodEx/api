<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Customizable box for joing two pipelines in-between. There are only one input
 * port and only one output port, ports can be modified.
 * Default values for ports and name are not implemented.
 * @note Should be used only for internal purposes.
 */
class JoinPipelinesBox extends Box
{
  /** Type key */
  public static $JOIN_PIPELINES_BOX_TYPE = "join-pipelines";

  /**
   * JoinPipelinesBox constructor.
   * @param string $name
   */
  public function __construct(string $name = "") {
    parent::__construct((new BoxMeta)->setName($name));
  }


  /**
   * Set name of box.
   * @param string $name
   * @return JoinPipelinesBox
   */
  public function setName(string $name): JoinPipelinesBox {
    $this->meta->setName($name);
    return $this;
  }

  /**
   * Set input port of this box.
   * @param Port $port
   * @return JoinPipelinesBox
   */
  public function setInputPort(Port $port): JoinPipelinesBox {
    $this->meta->setInputPorts([$port]);
    return $this;
  }

  /**
   * Set output port of this box.
   * @param Port $port
   * @return JoinPipelinesBox
   */
  public function setOutputPort(Port $port): JoinPipelinesBox {
    $this->meta->setOutputPorts([$port]);
    return $this;
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$JOIN_PIPELINES_BOX_TYPE;
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
    if (current($this->getInputPorts())->getVariableValue()->getValue() ===
        current($this->getOutputPorts())->getVariableValue()->getValue()) {
      return [];
    }

    // if values in ports are different then we should engage rename task
    $task = new Task();
    $task->setCommandBinary("rename");
    $task->setCommandArguments([
      ConfigParams::$SOURCE_DIR . current($this->getInputPorts())->getVariableValue()->getValue(),
      ConfigParams::$SOURCE_DIR . current($this->getOutputPorts())->getVariableValue()->getValue()
    ]);
    return [$task];
  }

}
