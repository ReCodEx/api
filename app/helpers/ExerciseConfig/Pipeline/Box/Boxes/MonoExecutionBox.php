<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which represents execution of assembly in C# which will be executed with
 * mono.
 */
class MonoExecutionBox extends ExecutionBox
{
  /** Type key */
  public static $MONO_EXEC_TYPE = "mono-exec";
  public static $MONO_BINARY = "/usr/bin/mono";
  public static $ASSEMBLY_FILE_PORT_KEY = "assembly";
  public static $DEFAULT_NAME = "Mono Execution";

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
        new Port((new PortMeta)->setName(self::$EXECUTION_ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
        new Port((new PortMeta)->setName(self::$STDIN_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta)->setName(self::$INPUT_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)),
        new Port((new PortMeta)->setName(self::$ASSEMBLY_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$STDOUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta)->setName(self::$OUTPUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
    }
  }

  /**
   * ElfExecutionBox constructor.
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
    return self::$MONO_EXEC_TYPE;
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
   * @throws ExerciseConfigException
   */
  public function compile(CompilationParams $params): array {
    $task = $this->compileBaseTask($params);
    $task->setCommandBinary(self::$MONO_BINARY);

    $args = [$this->getInputPortValue(self::$ASSEMBLY_FILE_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR)];
    if ($this->hasInputPortValue(self::$EXECUTION_ARGS_PORT_KEY)) {
      $args = array_merge($args, $this->getInputPortValue(self::$EXECUTION_ARGS_PORT_KEY)->getValue());
    }
    $task->setCommandArguments($args);

    return [$task];
  }

}
