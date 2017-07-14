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
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$defaultInputPorts || !self::$defaultOutputPorts) {
      self::$defaultInputPorts = array(
        new FilePort((new PortMeta)->setName("actual_output")->setVariable("")),
        new FilePort((new PortMeta)->setName("expected_output")->setVariable(""))
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

}
