<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which represents data source, mainly files.
 */
class FetchFileBox extends FetchBox
{
  /** Type key */
  public static $FETCH_TYPE = "fetch-file";
  public static $REMOTE_PORT_KEY = "remote";
  public static $INPUT_PORT_KEY = "input";
  public static $DEFAULT_NAME = "Fetch Pipeline File";

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
        new Port((new PortMeta)->setName(self::$REMOTE_PORT_KEY)->setType(VariableTypes::$REMOTE_FILE_TYPE))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$INPUT_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
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
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$FETCH_TYPE;
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
   * @throws \App\Exceptions\ExerciseConfigException
   */
  public function compile(CompilationParams $params): array {
    $remoteVariables = $this->getInputPortValue(self::$REMOTE_PORT_KEY);
    $variable = $this->getOutputPortValue(self::$INPUT_PORT_KEY);
    return $this->compileInternal($remoteVariables, $variable, $params );
  }

}
