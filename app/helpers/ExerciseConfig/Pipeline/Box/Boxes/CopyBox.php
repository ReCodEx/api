<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which will copy given file. If the filename is same for the input and
 * output box will compile to no-op.
 */
class CopyBox extends Box
{
  /** Type key */
  public static $COPY_TYPE = "copy";
  public static $COPY_PORT_IN_KEY = "in";
  public static $COPY_PORT_OUT_KEY = "out";
  public static $DEFAULT_NAME = "Copy";

  private static $initialized = false;
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = array(
        new Port((new PortMeta)->setName(self::$COPY_PORT_IN_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable(""))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$COPY_PORT_OUT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable(""))
      );
    }
  }


  /**
   * DataInBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$COPY_TYPE;
  }

  /**
   * Get default input ports for this box.
   * @return array
   */
  public function getDefaultInputPorts(): array {
    self::init();
    return self::$defaultInputPorts;
  }

  /**
   * Get default output ports for this box.
   * @return array
   */
  public function getDefaultOutputPorts(): array {
    self::init();
    return self::$defaultOutputPorts;
  }

  /**
   * Get default name of this box.
   * @return string
   */
  public function getDefaultName(): string {
    return self::$DEFAULT_NAME;
  }


  /**
   * Compile box into set of low-level tasks.
   * @return Task[]
   */
  public function compile(): array {
    if ($this->getInputPort(self::$COPY_PORT_IN_KEY)->getVariableValue()->getValue() ===
        $this->getOutputPort(self::$COPY_PORT_OUT_KEY)->getVariableValue()->getValue()) {
      return [];
    }

    $task = new Task();
    $task->setCommandBinary("cp");
    $task->setCommandArguments([
      $this->getInputPort(self::$COPY_PORT_IN_KEY)->getVariableValue()->getValue(),
      $this->getOutputPort(self::$COPY_PORT_OUT_KEY)->getVariableValue()->getValue()
    ]);
    return [$task];
  }

}
