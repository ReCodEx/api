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
 * Box that compile Bison format into C/C++ sources.
 */
class BisonCompilationBox extends CompilationBox
{
  /** Type key */
  public static $BOX_TYPE = "bison";
  public static $BISON_BINARY = "/usr/bin/bison";
  public static $BISON_EXT = ".y";
  public static $DEFAULT_NAME = "Bison Compilation";
  public static $OUTPUT_FILES_PORT_KEY  = "output";

  private static $initialized = false;
  private static $defaultOutputPorts;
  private static $defaultInputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = [
        new Port((new PortMeta())->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
        new Port((new PortMeta())->setName(self::$SOURCE_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
      ];
      self::$defaultOutputPorts = [
        new Port((new PortMeta())->setName(self::$OUTPUT_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
      ];
    }
  }

  /**
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
   */
  public function compile(CompilationParams $params): array {
    $task = $this->compileBaseTask($params);
    $task->setCommandBinary(self::$BISON_BINARY);

    $inputFile = $this->getInputPortValue(self::$SOURCE_FILE_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR);
    $inputBaseName = basename($inputFile, self::$BISON_EXT);

    // Prepare cmdline args
    $args = [];
    if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
      $args = $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue();
    }
    $args[] = "-o${inputBaseName}.cpp";
    $args[] = $inputFile;
    $task->setCommandArguments($args);

    // Set output names correctly and create task that will check their existence
    $output = $this->getOutputPortValue(self::$OUTPUT_FILES_PORT_KEY);
    $output->setValue([ "${inputBaseName}.cpp", "${inputBaseName}.hpp", "stack.hh" ]);
    $exists = $this->compileExistsTask($output->getDirPrefixedValue(ConfigParams::$SOURCE_DIR));

    return [$task, $exists];
  }

}
