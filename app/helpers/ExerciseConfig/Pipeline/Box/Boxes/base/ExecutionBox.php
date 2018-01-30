<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;
use Nette\Utils\Random;


/**
 * Box which represents execution of custom program. Execution task type is
 * special task which is supposed to run user provided programs and is checked
 * against time and memory limits.
 */
abstract class ExecutionBox extends Box
{
  public static $EXECUTION_ARGS_PORT_KEY = "args";
  public static $INPUT_FILES_PORT_KEY = "input-files";
  public static $STDIN_FILE_PORT_KEY = "stdin";
  public static $OUTPUT_FILE_PORT_KEY = "output-file";
  public static $STDOUT_FILE_PORT_KEY = "stdout";


  /**
   * ExecutionBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }


  /**
   * Base compilation which creates task, set its type to execution and create
   * sandbox configuration. Stdin and stdout are also handled here.
   * @param CompilationParams $params
   * @return Task
   * @throws ExerciseConfigException
   */
  protected function compileBaseTask(CompilationParams $params): Task {
    $task = new Task();
    $task->setPriority(Priorities::$EXECUTION);
    $task->setType(TaskType::$EXECUTION);

    $sandbox = (new SandboxConfig)->setName(LinuxSandbox::$ISOLATE);
    if ($this->hasInputPortValue(self::$STDIN_FILE_PORT_KEY)) {
      $sandbox->setStdin($this->getInputPortValue(self::$STDIN_FILE_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR));
    }
    if ($this->getOutputPort(self::$STDOUT_FILE_PORT_KEY)->getVariableValue() !== null) {
      $stdoutValue = $this->getOutputPortValue(self::$STDOUT_FILE_PORT_KEY);
      if ($stdoutValue->isEmpty()) {
        // name of the file is empty, so just make up some appropriate one
        $stdoutValue->setValue(Random::generate(20) . ".stdout");
      }
      $sandbox->setStdout($stdoutValue->getPrefixedValue(ConfigParams::$EVAL_DIR));
    }
    if ($params->isDebug()) {
      $stderrRandom = Random::generate(20) . ".stderr";
      $stderrName = ConfigParams::$EVAL_DIR . $stderrRandom;
      if ($params->getCurrentTestName()) {
        $stderrName = ConfigParams::$EVAL_DIR . $params->getCurrentTestName() . "/" . $stderrRandom;
      }
      // all stderrs are stored alongside solution in case of debugging submission
      $sandbox->setStderr($stderrName);
    }

    $task->setSandboxConfig($sandbox);

    return $task;
  }

}
