<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Box which will take two file arrays on its input and join them to one merged
 * array.
 */
class MergeFileAndFilesBox extends Box
{
  /** Type key */
  public static $MERGE_FILE_AND_FILES_TYPE = "merge-file-and-files";
  public static $IN1_PORT_KEY = "in1";
  public static $IN2_PORT_KEY = "in2";
  public static $OUT_PORT_KEY = "out";
  public static $DEFAULT_NAME = "Merge file with array of files";

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
        new Port((new PortMeta())->setName(self::$IN1_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta())->setName(self::$IN2_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)),
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta())->setName(self::$OUT_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
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
    return self::$MERGE_FILE_AND_FILES_TYPE;
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
   * @throws ExerciseConfigException
   */
  public function compile(CompilationParams $params): array {
    // will not produce tasks, only merge files during compilation
    $out = $this->getInputPortValue(self::$IN2_PORT_KEY)->getValueAsArray();
    $out[] = $this->getInputPortValue(self::$IN1_PORT_KEY)->getValue();
    $this->getOutputPortValue(self::$OUT_PORT_KEY)->setValue($out);
    return [];
  }
}
