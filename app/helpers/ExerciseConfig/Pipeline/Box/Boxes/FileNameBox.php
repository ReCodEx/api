<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\BoxCategories;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Takes file and produces textual file name (including the path).
 * This can be useful when injecting file into command line arguments.
 */
class FileNameBox extends Box
{
  public static $BOX_TYPE = "file-name";
  public static $DEFAULT_NAME = "File name";

  /** Type key */
  public static $IN_PORT_KEY = "in";
  public static $OUT_PORT_KEY = "out";

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
        new Port((new PortMeta())->setName(self::$IN_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta())->setName(self::$OUT_PORT_KEY)->setType(VariableTypes::$STRING_TYPE))
      );
    }
  }


  /**
   * DataInBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    self::init();
    parent::__construct($meta);
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$BOX_TYPE;
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
    // will not produce tasks, only convert array of files into their relative paths
    $in = $this->getInputPortValue(self::$IN_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR);
    $this->getOutputPortValue(self::$OUT_PORT_KEY)->setValue($in);
    return [];
  }
}
