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
 * Box which will dump given directory into results archive.
 */
class DumpResultsBox extends Box
{
  /** Type key */
  public static $DUMP_RESULTS_TYPE = "dump-results";
  public static $DUMP_RESULTS_PORT_IN_KEY = "dir";
  public static $DEFAULT_NAME = "Dump given directory in result archive";

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
        new Port((new PortMeta())->setName(self::$DUMP_RESULTS_PORT_IN_KEY)->setType(VariableTypes::$FILE_TYPE))
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
   * @return DumpResultsBox
   */
  public function setInputPort(Port $port): DumpResultsBox {
    $this->meta->setInputPorts([$port]);
    return $this;
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$DUMP_RESULTS_TYPE;
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
    $task->setPriority(Priorities::$DUMP_RESULTS);
    $task->setCommandBinary(TaskCommands::$DUMPDIR);

    $port = current($this->getInputPorts());
    $task->setCommandArguments([
      $port->getVariableValue()->getDirPrefixedValue(ConfigParams::$SOURCE_DIR),
      $port->getVariableValue()->getDirPrefixedValue(ConfigParams::$RESULT_DIR),
      ConfigParams::$DUMPDIR_LIMIT
    ]);

    return [$task];
  }

}
