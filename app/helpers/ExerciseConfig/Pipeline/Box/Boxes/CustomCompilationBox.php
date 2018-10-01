<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents custom compilation unit.
 * It can be used for any compilation preprocessing or postprocessing,
 * like custom macro processing, bison&flex compilation, or additional executable bundling.
 */
class CustomCompilationBox extends CompilationBox
{
  /** Type key */
  public static $CUSTOM_COMPILATION_TYPE = "custom-compilation";
  public static $DEFAULT_NAME = "Custom Compilation";
  public static $COMPILER_EXEC_PORT_KEY = "compiler-exec";

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
        new Port((new PortMeta())->setName(self::$COMPILER_EXEC_PORT_KEY)->setType(VariableTypes::$STRING_TYPE)),
        new Port((new PortMeta())->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
        new Port((new PortMeta())->setName(self::$SOURCE_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)),
        new Port((new PortMeta())->setName(self::$EXTRA_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta())->setName(self::$BINARY_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
    }
  }

  /**
   * JudgeNormalBox constructor.
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
    return self::$CUSTOM_COMPILATION_TYPE;
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
    $task = $this->compileBaseTask($params);
    $task->setCommandBinary($this->getInputPortValue(self::$COMPILER_EXEC_PORT_KEY)->getValue());

    // Get files that should be injected into args....
    $binaryFile = $this->getOutputPortValue(self::$BINARY_FILE_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR);
    $injections = [
      self::$SOURCE_FILES_PORT_KEY =>
        $this->getInputPortValue(self::$SOURCE_FILES_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR),
      self::$EXTRA_FILES_PORT_KEY =>
        $this->getInputPortValue(self::$EXTRA_FILES_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR),
      self::$BINARY_FILE_PORT_KEY => [ $binaryFile ],
    ];

    // Process args
    $rawArgs = [];
    if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
      $rawArgs = $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue();
    }

    $args = [];
    foreach ($rawArgs as $arg) {
      if (substr($arg, 0, 2) === '$@') {
        $name = substr($arg, 2);
        if ($injections[$name]) {
          array_push($args, ...$injections[$name]);
          unset($injections[$name]);
        }
      } else {
        $args[] = $arg;
      }
    }

    // If no placeholders were found, append the files in typical manner....
    if (!empty($injections[self::$SOURCE_FILES_PORT_KEY])) {
      array_push($args, ...$injections[self::$SOURCE_FILES_PORT_KEY]);
    }
    if (!empty($injections[self::$EXTRA_FILES_PORT_KEY])) {
      array_push($args, ...$injections[self::$EXTRA_FILES_PORT_KEY]);
    }
    if (!empty($injections[self::$BINARY_FILE_PORT_KEY])) {
      $args[] = "-o";
      $args[] = $binaryFile;
    }

    $task->setCommandArguments($args);

    // check if file produced by compilation was successfully created
    $binary = $this->getOutputPortValue(self::$BINARY_FILE_PORT_KEY)->getTestPrefixedValue(ConfigParams::$SOURCE_DIR);
    $exists = $this->compileExistsTask([$binary]);

    return [$task, $exists];
  }

}
