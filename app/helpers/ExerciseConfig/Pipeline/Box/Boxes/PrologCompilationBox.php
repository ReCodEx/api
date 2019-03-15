<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use Exception;


/**
 * Box which represents compilation of given prolog scripts.
 */
class PrologCompilationBox extends CompilationBox
{
  /** Type key */
  public static $BOX_TYPE = "prolog-compilation";
  public static $PROLOG_BINARY = "/usr/bin/swipl";
  public static $DEFAULT_NAME = "SWI-Prolog Compilation";
  public static $WRAPPER_FILE_PORT_KEY = "compilation-wrapper";

  private static $initialized = false;
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function init() {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = array(
        new Port((new PortMeta())->setName(self::$WRAPPER_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta())->setName(self::$RUNNER_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
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
   * Constructor.
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
   * @throws ExerciseConfigException
   */
  public function getDefaultInputPorts(): array {
    self::init();
    return self::$defaultInputPorts;
  }

  /**
   * Get default output ports for this box.
   * @return array
   * @throws ExerciseConfigException
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
   * @throws Exception
   */
  public function compile(CompilationParams $params): array {
    $task = $this->compileBaseTask($params);

    // prolog has special wrapper runner
    $wrapper = $this->getInputPortValue(self::$WRAPPER_FILE_PORT_KEY)->getValue();
    $task->setCommandBinary($wrapper);

    $args = [ self::$PROLOG_BINARY ];
    if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
      $args = array_merge($args,
        $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue());
    }

    array_push($args,
      "-o",
      $this->getOutputPortValue(self::$BINARY_FILE_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR),
      "-c"
    );

    $task->setCommandArguments(
      array_merge(
        $args,
        $this->getInputPortValue(self::$SOURCE_FILES_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR),
        $this->getInputPortValue(self::$EXTRA_FILES_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR),
        [ $this->getInputPortValue(self::$RUNNER_FILE_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR) ]
      )
    );

    // check if file produced by compilation was successfully created
    $binary = $this->getOutputPortValue(self::$BINARY_FILE_PORT_KEY)->getDirPrefixedValue(ConfigParams::$SOURCE_DIR);
    $exists = $this->compileExistsTask([$binary]);

    return [$task, $exists];
  }

}
