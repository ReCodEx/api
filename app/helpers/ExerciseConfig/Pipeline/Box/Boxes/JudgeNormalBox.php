<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;


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
        (new Port)->setName("actual_output")->setVariable(""),
        (new Port)->setName("expected_output")->setVariable("")
      );
      self::$defaultOutputPorts = array(
        (new Port)->setName("score")->setVariable("")
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
