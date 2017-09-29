<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents judge evaluation.
 */
class JudgeBox extends Box
{
  /** Type key */
  public static $JUDGE_TYPE = "judge";
  public static $JUDGE_TYPE_PORT_KEY = "judge-type";
  public static $ACTUAL_OUTPUT_PORT_KEY = "actual-output";
  public static $EXPECTED_OUTPUT_PORT_KEY = "expected-output";
  public static $DEFAULT_NAME = "ReCodEx Judge";

  /* TYPES OF JUDGES */
  public static $RECODEX_NORMAL_TYPE = "recodex-judge-normal";
  public static $RECODEX_SHUFFLE_TYPE = "recodex-judge-shuffle";
  public static $DIFF_TYPE = "diff";
  public static $DIFF_BINARY = "/usr/bin/diff";

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
        new Port((new PortMeta)->setName(self::$JUDGE_TYPE_PORT_KEY)->setType(VariableTypes::$STRING_TYPE)),
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
    return self::$JUDGE_TYPE;
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
   * Determine and return judge execution binary.
   * @return array pair of binary and list of arguments
   * @throws ExerciseConfigException
   */
  private function getJudgeBinaryAndArgs(): array {
    $judgeType = null;
    if ($this->hasInputPortValue(self::$JUDGE_TYPE_PORT_KEY)) {
      $judgeType = $this->getInputPortValue(self::$JUDGE_TYPE_PORT_KEY)->getValue();
    }

    // judge type decision logic
    if (empty($judgeType) || strtolower($judgeType) === self::$RECODEX_NORMAL_TYPE) {
      return [ConfigParams::$JUDGES_DIR . self::$RECODEX_NORMAL_TYPE, []];
    } else if (strtolower($judgeType) === self::$RECODEX_SHUFFLE_TYPE) {
      return [ConfigParams::$JUDGES_DIR . self::$RECODEX_SHUFFLE_TYPE, []];
    } else if (strtolower($judgeType) === self::$DIFF_TYPE) {
      return [self::$DIFF_BINARY, []];
    }

    throw new ExerciseConfigException("Unknown judge type");
  }

  /**
   * Compile box into set of low-level tasks.
   * @param CompilationParams $params
   * @return array
   */
  public function compile(CompilationParams $params): array {
    $task = new Task();
    $task->setType(TaskType::$EVALUATION);

    list($binary, $args) = $this->getJudgeBinaryAndArgs();
    $task->setCommandBinary($binary);
    $task->setCommandArguments(
      array_merge(
        $args,
        [
          $this->getInputPortValue(self::$EXPECTED_OUTPUT_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR),
          $this->getInputPortValue(self::$ACTUAL_OUTPUT_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR)
        ]
      )
    );

    $sandbox = (new SandboxConfig)->setName(LinuxSandbox::$ISOLATE);
    $sandbox->setOutput(true);
    $task->setSandboxConfig($sandbox);

    return [$task];
  }

}
