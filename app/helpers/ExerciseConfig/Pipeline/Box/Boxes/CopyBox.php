<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
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
        new Port((new PortMeta)->setName(self::$COPY_PORT_IN_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$COPY_PORT_OUT_KEY)->setType(VariableTypes::$FILE_TYPE))
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
   * @param CompilationParams $params
   * @return array
   */
  public function compile(CompilationParams $params): array {
    if ($this->getInputPortValue(self::$COPY_PORT_IN_KEY)->getPrefixedValue() ===
        $this->getOutputPortValue(self::$COPY_PORT_OUT_KEY)->getPrefixedValue()) {
      return [];
    }

    $task = new Task();
    $task->setPriority(Priorities::$DEFAULT);
    $task->setCommandBinary(TaskCommands::$COPY);
    $task->setCommandArguments([
      $this->getInputPortValue(self::$COPY_PORT_IN_KEY)->getPrefixedValue(ConfigParams::$SOURCE_DIR),
      $this->getOutputPortValue(self::$COPY_PORT_OUT_KEY)->getPrefixedValue(ConfigParams::$SOURCE_DIR)
    ]);
    return [$task];
  }

}
