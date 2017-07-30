<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;
use App\Helpers\ExerciseConfig\Pipeline\Ports\FilePort;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Pipeline\Ports\StringPort;


/**
 * Box which represents recodex-judge-normal executable.
 */
class JudgeNormalBox extends Box
{
  private static $initialized = false;
  private static $defaultName;
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultName = "ReCodEx Judge Normal";
      self::$defaultInputPorts = array(
        new FilePort((new PortMeta)->setName("actual-output")->setVariable("")),
        new FilePort((new PortMeta)->setName("expected-output")->setVariable(""))
      );
      self::$defaultOutputPorts = array(
        new StringPort((new PortMeta)->setName("score")->setVariable(""))
      );
    }
  }

  /**
   * JudgeNormalBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
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
    self::init();
    return self::$defaultName;
  }

}
