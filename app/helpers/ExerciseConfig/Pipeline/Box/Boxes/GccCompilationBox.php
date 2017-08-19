<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Ports\FilePort;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents gcc compilation unit.
 */
class GccCompilationBox extends Box
{
  /** Type key */
  public static $GCC_TYPE = "gcc";
  public static $GCC_BINARY = "/usr/bin/gcc";
  public static $SOURCE_FILE_PORT_KEY = "source-file";
  public static $BINARY_FILE_PORT_KEY = "binary-file";
  public static $DEFAULT_NAME = "GCC Compilation";

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
        new FilePort((new PortMeta)->setName(self::$SOURCE_FILE_PORT_KEY)->setVariable(""))
      );
      self::$defaultOutputPorts = array(
        new FilePort((new PortMeta)->setName(self::$BINARY_FILE_PORT_KEY)->setVariable(""))
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
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$GCC_TYPE;
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
    $task->setType(TaskType::$INITIATION);
    $task->setCommandBinary(self::$GCC_BINARY);
    $task->setCommandArguments([
      $this->getInputPort(self::$SOURCE_FILE_PORT_KEY)->getVariableValue()->getValue(),
      "-o",
      $this->getOutputPort(self::$BINARY_FILE_PORT_KEY)->getVariableValue()->getValue()
    ]);
    $task->setSandboxConfig((new SandboxConfig)->setName(LinuxSandbox::$ISOLATE));
    return [$task];
  }

}
