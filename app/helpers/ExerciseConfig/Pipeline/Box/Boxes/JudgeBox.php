<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
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
  public static $ARGS_PORT_KEY = "args";
  public static $JUDGE_TYPE_PORT_KEY = "judge-type";
  public static $CUSTOM_JUDGE_PORT_KEY = "custom-judge";
  public static $ACTUAL_OUTPUT_PORT_KEY = "actual-output";
  public static $EXPECTED_OUTPUT_PORT_KEY = "expected-output";
  public static $DEFAULT_NAME = "ReCodEx Judge";

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
        new Port((new PortMeta)->setName(self::$EXPECTED_OUTPUT_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta)->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
        new Port((new PortMeta)->setName(self::$CUSTOM_JUDGE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
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
    if ($this->hasInputPortValue(self::$CUSTOM_JUDGE_PORT_KEY) &&
        !$this->getInputPortValue(self::$CUSTOM_JUDGE_PORT_KEY)->isEmpty()) {
      // custom judge was provided
      return [$this->getInputPortValue(self::$CUSTOM_JUDGE_PORT_KEY)->getValue(), []];
    }

    $judgeType = null;
    if ($this->hasInputPortValue(self::$JUDGE_TYPE_PORT_KEY)) {
      $judgeType = strtolower($this->getInputPortValue(self::$JUDGE_TYPE_PORT_KEY)->getValue());
    }

  // Translation of judge type to command and args. The first item is the default.
  static $judgeTypes = null;
    if ($judgeTypes === null) {
      $judgeTypes = [
        'recodex-judge-normal' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-normal', []],               // default token judge (respecting newlines)
        'recodex-judge-float' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-normal', ['-r']],            // judge comparing float values with some margin of error
        'recodex-judge-normal-newline' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-normal', ['-n']],   // default token judge (which treats \n as normal whitespace)
        'recodex-judge-float-newline' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-normal', ['-rn']],   // judge comparing float values (which treats \n as normal whitespace)
        'recodex-judge-shuffle' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-shuffle', ['-i']],         // judge ignoring order of tokens on a line
        'recodex-judge-shuffle-rows' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-shuffle', ['-r']],    // judge ignoring order of rows
        'recodex-judge-shuffle-all' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-shuffle', ['-i','-r']],// judge ignoring order of tokens on a each line and order of rows
        'recodex-judge-shuffle-newline' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-shuffle', ['-i','-n']], // judge ignoring order of tokens (which treats \n ...)
        'diff' => ["/usr/bin/diff", []],                                                               // diff (binary-safe) judge
      ];
    }

    // judge type decision logic
    if (empty($judgeType)) {
      return reset($judgeTypes);
    } else if (!empty($judgeTypes[$judgeType])) {
      return $judgeTypes[$judgeType];
    } else {
      throw new ExerciseConfigException("Unknown judge type");
    }
  }

  /**
   * Compile box into set of low-level tasks.
   * @param CompilationParams $params
   * @return array
   */
  public function compile(CompilationParams $params): array {
    $task = new Task();
    $task->setPriority(Priorities::$EVALUATION);
    $task->setType(TaskType::$EVALUATION);

    list($binary, $args) = $this->getJudgeBinaryAndArgs();
    $task->setCommandBinary($binary);

    // custom args
    if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
      $args = array_merge($args, $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue());
    }

    // classical args: expected actual
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
