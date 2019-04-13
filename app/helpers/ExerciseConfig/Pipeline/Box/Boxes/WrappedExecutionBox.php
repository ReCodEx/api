<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which represents execution where the executable file needs to
 * be passed to a particular runtime. Optinally, the runtime may get
 * arguments of its own.
 */
class WrappedExecutionBox extends ExecutionBox
{
  /** Type key */
  public static $BOX_TYPE = "wrapped-exec";
  public static $BINARY_FILE_PORT_KEY = "binary-file";
  public static $RUNTIME_PATH_PORT_KEY = "runtime-path";
  public static $RUNTIME_ARGS_PORT_KEY = "runtime-args";
  public static $DEFAULT_NAME = "Wrapped Execution";

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
        new Port((new PortMeta())->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
        new Port((new PortMeta())->setName(self::$STDIN_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta())->setName(self::$INPUT_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)),
        new Port((new PortMeta())->setName(self::$BINARY_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta())->setName(self::$RUNTIME_PATH_PORT_KEY)->setType(VariableTypes::$STRING_TYPE)),
        new Port((new PortMeta())->setName(self::$RUNTIME_ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta())->setName(self::$STDOUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta())->setName(self::$OUTPUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
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
    return self::$BOX_TYPE;
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
    $task->setCommandBinary($this->getInputPortValue(self::$RUNTIME_PATH_PORT_KEY)->getValue());

    $args = $this->hasInputPortValue(self::$RUNTIME_ARGS_PORT_KEY)
      ? $this->getInputPortValue(self::$RUNTIME_ARGS_PORT_KEY)->getValue()
      : [];
    $args[] = $this->getInputPortValue(self::$BINARY_FILE_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR);
    if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
      $args = array_merge($args, $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue());
    }
    $task->setCommandArguments($args);

    return [$task];
  }

}
