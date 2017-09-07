<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents data source, mainly files.
 */
class DataInBox extends Box
{
  /** Type key */
  public static $DATA_IN_TYPE = "data-in";
  public static $DATA_IN_PORT_KEY = "in-data";
  public static $DEFAULT_NAME = "Input Data";

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
        new Port((new PortMeta)->setName(self::$DATA_IN_PORT_KEY)->setType(VariableTypes::$UNDEFINED_TYPE)->setVariable(""))
      );
    }
  }


  /**
   * If data for this box is remote, fill this with the right variable reference.
   * @var Variable
   */
  private $inputVariable = null;


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
    return self::$DATA_IN_TYPE;
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
   * Get remote variable.
   * @return Variable|null
   */
  public function getInputVariable(): ?Variable {
    return $this->inputVariable;
  }

  /**
   * Set remote variable corresponding to this box.
   * @param Variable|null $variable
   */
  public function setInputVariable(?Variable $variable) {
    $this->inputVariable = $variable;
  }


  /**
   * Compile box into set of low-level tasks.
   * @return array
   * @throws ExerciseConfigException in case of compilation error
   */
  public function compile(): array {
    if (!$this->inputVariable) {
      // should not happen
      return [];
    }

    // remote file which should be downloaded from file-server
    $inputVariable = $this->inputVariable;
    $variable = $this->getOutputPort(self::$DATA_IN_PORT_KEY)->getVariableValue();

    // validate variable value and prepare arrays which will be processed
    if ($inputVariable->isValueArray() && $variable->isValueArray()) {
      if (count($inputVariable->getValue()) !== count($variable->getValue())) {
        throw new ExerciseConfigException(sprintf("Different count of remote variables and local variables in box '%s'", self::$DATA_IN_TYPE));
      }

      $inputFiles = $inputVariable->getValue();
      $files = $variable->getValue();
    } else if (!$inputVariable->isValueArray() && !$variable->isValueArray()) {
      $inputFiles = [$inputVariable->getValue()];
      $files = [$variable->getValue()];
    } else {
      throw new ExerciseConfigException(sprintf("Remote variable and local variable both have different type in box '%s'", self::$DATA_IN_TYPE));
    }

    // general foreach for both local and remote files
    $tasks = [];
    for ($i = 0; $i < count($files); ++$i) {
      $task = new Task();

      if ($inputVariable->isRemoteFile()) {
        // remote file has to have fetch task
        $task->setCommandBinary("fetch");
        $task->setCommandArguments([
          $inputFiles[$i],
          ConfigParams::$SOURCE_DIR . $files[$i]
        ]);
      } else {
        if ($inputFiles[$i] === $files[$i]) {
          // files have exactly same names, we can skip renaming
          continue;
        }

        $task->setCommandBinary("rename");
        $task->setCommandArguments([
          ConfigParams::$SOURCE_DIR . $inputFiles[$i],
          ConfigParams::$SOURCE_DIR . $files[$i]
        ]);
      }

      // add task to result
      $tasks[] = $task;
    }
    return $tasks;
  }

}
