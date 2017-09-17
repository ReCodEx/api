<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents recodex-judge-normal executable.
 */
class JudgeNormalBox extends Box
{
  /** Type key */
  public static $JUDGE_NORMAL_TYPE = "judge-normal";
  public static $JUDGE_NORMAL_BINARY = "recodex-judge-normal";
  public static $ACTUAL_OUTPUT_PORT_KEY = "actual-output";
  public static $EXPECTED_OUTPUT_PORT_KEY = "expected-output";
  public static $DEFAULT_NAME = "ReCodEx Judge Normal";

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
        new Port((new PortMeta)->setName(self::$ACTUAL_OUTPUT_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta)->setName(self::$EXPECTED_OUTPUT_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
      self::$defaultOutputPorts = array();
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
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$JUDGE_NORMAL_TYPE;
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
   * @return Task[]
   */
  public function compile(): array {
    $task = new Task();
    $task->setType(TaskType::$EVALUATION);
    $task->setCommandBinary(ConfigParams::$JUDGES_DIR . self::$JUDGE_NORMAL_BINARY);
    $task->setCommandArguments([
      $this->getInputPortValue(self::$EXPECTED_OUTPUT_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR),
      $this->getInputPortValue(self::$ACTUAL_OUTPUT_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR)
    ]);

    $sandbox = (new SandboxConfig)->setName(LinuxSandbox::$ISOLATE);
    $sandbox->setOutput(true);
    $task->setSandboxConfig($sandbox);

    return [$task];
  }

}
