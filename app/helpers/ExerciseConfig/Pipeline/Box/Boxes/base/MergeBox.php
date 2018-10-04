<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use Exception;


/**
 * Base class for merging boxes. They take two arrays and produce concatenated array.
 */
class MergeBox extends Box
{
  public static $MERGE_TYPE = null;
  public static $DEFAULT_NAME = null;

  /** Type key */
  public static $IN1_PORT_KEY = "in1";
  public static $IN2_PORT_KEY = "in2";
  public static $OUT_PORT_KEY = "out";

  private static $initialized = false;
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   * @throws ExerciseConfigException
   * @throws Exception
   */
  public static function init() {
    throw new Exception("Unimplemented init method in MergeBox derived class.");
  }

  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function initMerger(string $baseType) {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = array(
        new Port((new PortMeta())->setName(self::$IN1_PORT_KEY)->setType($baseType)),
        new Port((new PortMeta())->setName(self::$IN2_PORT_KEY)->setType($baseType)),
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta())->setName(self::$OUT_PORT_KEY)->setType($baseType))
      );
    }
  }


  /**
   * DataInBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    static::init();
    parent::__construct($meta);
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$MERGE_TYPE;
  }

  /**
   * Get default input ports for this box.
   * @return array
   * @throws ExerciseConfigException
   */
  public function getDefaultInputPorts(): array {
    static::init();
    return self::$defaultInputPorts;
  }

  /**
   * Get default output ports for this box.
   * @return array
   * @throws ExerciseConfigException
   */
  public function getDefaultOutputPorts(): array {
    static::init();
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
    // will not produce tasks, only merge strings during compilation
    $in1 = $this->getInputPortValue(self::$IN1_PORT_KEY)->getValueAsArray();
    $in2 = $this->getInputPortValue(self::$IN2_PORT_KEY)->getValueAsArray();
    $out = array_merge($in1, $in2);
    $this->getOutputPortValue(self::$OUT_PORT_KEY)->setValue($out);
    return [];
  }
}
