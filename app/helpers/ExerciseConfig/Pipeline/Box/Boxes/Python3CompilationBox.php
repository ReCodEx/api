<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which represents compilation of python scripts.
 */
class Python3CompilationBox extends CompilationBox
{
  /** Type key */
  public static $PYTHON3_COMPILATION_TYPE = "python3c";
  public static $PYTHON3_BINARY = "/usr/bin/python3";
  public static $PYC_FILE_PORT_KEY = "pyc-file";
  public static $PYC_EXT = ".pyc";
  public static $DEFAULT_NAME = "Python3 Compilation";

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
        new Port((new PortMeta)->setName(self::$SOURCE_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta)->setName(self::$EXTRA_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$PYC_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
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
    return self::$PYTHON3_COMPILATION_TYPE;
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
    $task->setCommandBinary(self::$PYTHON3_BINARY);
    $task->setCommandArguments(["-m", "compileall", "-b", "."]);

    // determine name of pyc file and set it to variable
    $sourceFile = $this->getInputPortValue(self::$SOURCE_FILE_PORT_KEY)->getValue();
    $pycFilename = pathinfo($sourceFile, PATHINFO_FILENAME) . self::$PYC_EXT;
    $this->getOutputPortValue(self::$PYC_FILE_PORT_KEY)->setValue($pycFilename);

    // check if file produced by compilation was successfully created
    $pycFile = $this->getOutputPortValue(self::$PYC_FILE_PORT_KEY)->getPrefixedValue(ConfigParams::$SOURCE_DIR);
    $exists = $this->compileExistsTask([$pycFile]);

    return [$task, $exists];
  }

}