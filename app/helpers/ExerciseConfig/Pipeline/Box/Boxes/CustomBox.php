<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;
use App\Helpers\ExerciseConfig\Pipeline\Ports\FilePort;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Pipeline\Ports\UndefinedPort;


/**
 * Customizable box whose ports can be modified.
 * @note Should be used only for internal purposes.
 */
class CustomBox extends Box
{
  /**
   * CustomBox constructor.
   */
  public function __construct() {
    parent::__construct(new BoxMeta());
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
   * Add output port of this box.
   * @param Port $port
   * @return CustomBox
   */
  public function addOutputPort(Port $port): CustomBox {
    $this->meta->addOutputPort($port);
    return $this;
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

}
