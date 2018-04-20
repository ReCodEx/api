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
 * Box which will create directory which was requested
 */
class MkdirBox extends Box
{
  /** Type key */
  public static $MKDIR_TYPE = "mkdir";
  public static $MKDIR_PORT_IN_KEY = "in";
  public static $DEFAULT_NAME = "Make directory";

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
        new Port((new PortMeta)->setName(self::$MKDIR_PORT_IN_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
      self::$defaultOutputPorts = array();
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
   * Set input port of this box.
   * @param Port $port
   * @return MkdirBox
   */
  public function setInputPort(Port $port): MkdirBox {
    $this->meta->setInputPorts([$port]);
    return $this;
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$MKDIR_TYPE;
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
    $task = new Task();
    $task->setPriority(Priorities::$DEFAULT);
    $task->setCommandBinary(TaskCommands::$MKDIR);
    $task->setCommandArguments([
      current($this->getInputPorts())->getVariableValue()->getTestPrefixedValue(ConfigParams::$SOURCE_DIR)
    ]);
    return [$task];
  }

}
