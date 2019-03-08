<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\BoxCategories;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use Exception;


/**
 * Base for conversion boxes which take a scalar and produce a single-item array.
 */
abstract class ScalarToArrayBox extends Box
{
  /** Type key */
  public static $IN_PORT_KEY = "in";
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
    throw new Exception("Unimplemented init method in ScalarToArrayBox derived class.");
  }

  /**
   * Static initializer.
   * @throws ExerciseConfigException
   */
  public static function initScalarToArray(string $scalarType, string $arrayType) {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = array(
        new Port((new PortMeta())->setName(self::$IN_PORT_KEY)->setType($scalarType)),
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta())->setName(self::$OUT_PORT_KEY)->setType($arrayType))
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


  public function getCategory(): string {
    return BoxCategories::$INNER;
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
   * Compile box into set of low-level tasks.
   * @param CompilationParams $params
   * @return array
   * @throws ExerciseConfigException
   */
  public function compile(CompilationParams $params): array {
    if ($this->getInputPortValue(self::$IN_PORT_KEY)->isEmpty()) {
      // pointless usage of box, but hell, we have to be ready for that
      $this->getOutputPortValue(self::$OUT_PORT_KEY)->setValue(null);
      return [];
    }

    // will not produce tasks, only convert scalar value into single-item array
    $in = $this->getInputPortValue(self::$IN_PORT_KEY)->getValue();
    $this->getOutputPortValue(self::$OUT_PORT_KEY)->setValue([ $in ]);
    return [];
  }
}
