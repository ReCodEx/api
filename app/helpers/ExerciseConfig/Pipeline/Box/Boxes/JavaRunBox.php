<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use Nette\Utils\Strings;


/**
 * Run Java with ReCodEx Runner.
 */
class JavaRunBox extends ExecutionBox
{
  /** Type key */
  public static $JAVA_RUNNER_TYPE = "java-runner";
  public static $JAVA_BINARY = "/usr/bin/java";
  public static $CLASS_FILES_PORT_KEY = "class-files";
  public static $JAR_FILES_PORT_KEY = "jar-files";
  public static $DEFAULT_NAME = "Java Runner";

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
        new Port((new PortMeta())->setName(self::$RUNNER_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta())->setName(self::$EXECUTION_ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
        new Port((new PortMeta())->setName(self::$STDIN_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta())->setName(self::$INPUT_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)),
        new Port((new PortMeta())->setName(self::$CLASS_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)),
        new Port((new PortMeta())->setName(self::$JAR_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta())->setName(self::$STDOUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta())->setName(self::$OUTPUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
    }
  }

  /**
   * ElfExecutionBox constructor.
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
    return self::$JAVA_RUNNER_TYPE;
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
    $task = $this->compileBaseTask($params);
    $task->setCommandBinary(self::$JAVA_BINARY);

    // well we are running java and java is not smart enough to derive class
    // name from class filename, so we are gonna be nice and do this tedious job
    // instead of java runtime, you are welcome
    $runnerClass = $this->getInputPortValue(self::$RUNNER_FILE_PORT_KEY)->getValue();
    if (Strings::endsWith($runnerClass, ".class")) {
      $runnerLength = Strings::length($runnerClass);
      $runnerClass = Strings::substring($runnerClass, 0, $runnerLength - 6);
    }

    $args = [];
    // if there were some provided jar files, lets add them to the command line args
    $classpath = JavaUtils::constructClasspath($this->getInputPortValue(self::$JAR_FILES_PORT_KEY));
    $args = array_merge($args, $classpath);

    $args = array_merge($args, [ $runnerClass, "run" ]);
    if ($this->hasInputPortValue(self::$EXECUTION_ARGS_PORT_KEY)) {
      $args = array_merge($args, $this->getInputPortValue(self::$EXECUTION_ARGS_PORT_KEY)->getValue());
    }
    $task->setCommandArguments($args);

    return [$task];
  }

}
