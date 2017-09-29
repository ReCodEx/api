<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents external files source.
 */
class FilesInBox extends DataInBox
{
  /** Type key */
  public static $FILES_IN_TYPE = "files-in";
  public static $FILES_IN_PORT_KEY = "input";
  public static $DEFAULT_NAME = "Input Files";

  private static $initialized = false;
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = array();
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$FILES_IN_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
      );
    }
  }


  /**
   * FilesInBox constructor.
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
    return self::$FILES_IN_TYPE;
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
   * Get variable name of the output port.
   * @return null|string
   */
  public function getVariableName(): ?string {
    return $this->getOutputPort(self::$FILES_IN_PORT_KEY)->getVariable();
  }


  /**
   * Compile box into set of low-level tasks.
   * @param CompilationParams $params
   * @return Task[]
   * @throws ExerciseConfigException in case of compilation error
   */
  public function compile(CompilationParams $params): array {

    // remote file which should be downloaded from file-server
    $inputVariable = $this->inputVariable;
    $variable = $this->getOutputPortValue(self::$FILES_IN_PORT_KEY);

    // input variable is not array
    if ($inputVariable && !$inputVariable->isValueArray()) {
      throw new ExerciseConfigException(sprintf("Remote variable and local variable both have different type in box '%s'", self::$FILES_IN_TYPE));
    }

    // both variable and input variable are arrays
    if ($inputVariable && (count($inputVariable->getValue()) !== count($variable->getValue()))) {
      throw new ExerciseConfigException(sprintf("Different count of remote variables and local variables in box '%s'", self::$FILES_IN_TYPE));
    }

    // compilation
    return $this->compileInternal($inputVariable, $variable);
  }

}
