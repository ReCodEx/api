<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;


/**
 * Abstract base class for internal and external tasks which stores all shared info.
 */
abstract class TaskBase {

  /** Config key which represents task identification */
  const TASK_ID_KEY = "task-id";
  /** Key representing priority of task */
  const PRIORITY_KEY = "priority";
  /** Config key which represents if task has fatal failure bit set */
  const FATAL_FAILURE_KEY = "fatal-failure";
  /** Key representing dependencies collection */
  const DEPENDENCIES = "dependencies";
  /** Key representing command map */
  const CMD_KEY = "cmd";
  /** Command binary key */
  const CMD_BIN_KEY = "bin";
  /** Command arguments key */
  const CMD_ARGS_KEY = "args";
  /** Test identification key */
  const TEST_ID_KEY = "test-id";
  /** Task type key */
  const TYPE_KEY = "type";

  /** @var string Task ID */
  protected $id;
  /** @var integer Priority of task, higher number means higher priority */
  protected $priority;
  /** @var boolean If true execution of whole job will be stopped on failure */
  protected $fatalFailure;
  /** @var array Collection of dependencies */
  protected $dependencies = [];
  /** @var string Binary which will be executed in this task */
  protected $commandBinary;
  /** @var array Arguments for execution command */
  protected $commandArguments = [];
  /** @var string Type of the task */
  protected $type = NULL;
  /** @var string ID of the test to which this task corresponds */
  protected $testId = NULL;
  /** @var array Additional data */
  protected $data = [];

  /**
   * Construction which takes structured configuration in form of array.
   * @param array $data Structured config
   * @throws JobConfigLoadingException In case of any parsing error
   */
  public function __construct(array $data) {

    // *** LOAD MANDATORY ITEMS

    if (!isset($data[self::TASK_ID_KEY])) {
      throw new JobConfigLoadingException("Task configuration does not contain required '" . self::TASK_ID_KEY . "' field.");
    }
    $this->id = $data[self::TASK_ID_KEY];
    unset($data[self::TASK_ID_KEY]);

    if (!isset($data[self::PRIORITY_KEY])) {
      throw new JobConfigLoadingException("Task configuration does not contain required '" . self::PRIORITY_KEY . "' field.");
    }
    $this->priority = intval($data[self::PRIORITY_KEY]);
    unset($data[self::PRIORITY_KEY]);

    if (!isset($data[self::FATAL_FAILURE_KEY])) {
      throw new JobConfigLoadingException("Task configuration does not contain required '" . self::FATAL_FAILURE_KEY . "' field.");
    }
    $this->fatalFailure = filter_var($data[self::FATAL_FAILURE_KEY], FILTER_VALIDATE_BOOLEAN);
    unset($data[self::FATAL_FAILURE_KEY]);

    if (!isset($data[self::CMD_KEY]) || !is_array($data[self::CMD_KEY])) {
      throw new JobConfigLoadingException("Task configuration does not contain proper '" . self::CMD_KEY . "' field.");
    }

    if (!isset($data[self::CMD_KEY][self::CMD_BIN_KEY])) {
      throw new JobConfigLoadingException("Task configuration does not contain required '" . self::CMD_KEY . "." . self::CMD_BIN_KEY . "' field.");
    }
    $this->commandBinary = $data[self::CMD_KEY][self::CMD_BIN_KEY];
    unset($data[self::CMD_KEY][self::CMD_BIN_KEY]);

    // *** LOAD OPTIONAL ITEMS

    if (isset($data[self::DEPENDENCIES]) && is_array($data[self::DEPENDENCIES])) {
      $this->dependencies = $data[self::DEPENDENCIES];
      unset($data[self::DEPENDENCIES]);
    }

    if (isset($data[self::CMD_KEY][self::CMD_ARGS_KEY]) && is_array($data[self::CMD_KEY][self::CMD_ARGS_KEY])) {
      $this->commandArguments = $data[self::CMD_KEY][self::CMD_ARGS_KEY];
      unset($data[self::CMD_KEY][self::CMD_ARGS_KEY]);
    }

    if (isset($data[self::TYPE_KEY])) {
      $this->type = $data[self::TYPE_KEY];
      unset($data[self::TYPE_KEY]);
    }

    if (isset($data[self::TEST_ID_KEY])) {
      $this->testId = $data[self::TEST_ID_KEY];
      unset($data[self::TEST_ID_KEY]);
    }

    // *** LOAD REMAINING DATA
    $this->data = $data;
  }

  /**
   * ID of the task itself.
   * @return string
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Priority of this task.
   * @return int
   */
  public function getPriority(): int {
    return $this->priority;
  }

  /**
   * Fatal failure bit.
   * @return bool
   */
  public function getFatalFailure(): bool {
    return $this->fatalFailure;
  }

  /**
   * Gets array of dependencies.
   * @return array
   */
  public function getDependencies(): array {
    return $this->dependencies;
  }

  /**
   * Returns command binary.
   * @return string
   */
  public function getCommandBinary(): string {
    return $this->commandBinary;
  }

  /**
   * Gets command arguments.
   * @return array
   */
  public function getCommandArguments(): array {
    return $this->commandArguments;
  }

  /**
   * Type of the task
   * @return string|NULL
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Check if task has type initiation or not.
   * @return bool
   */
  public function isInitiationTask(): bool {
    return $this->type === InitiationTaskType::TASK_TYPE;
  }

  /**
   * Check if task is of type execution or not.
   * @return bool
   */
  public function isExecutionTask(): bool {
    return $this->type === ExecutionTaskType::TASK_TYPE;
  }

  /**
   * Checks if task has type evaluation or not.
   * @return bool
   */
  public function isEvaluationTask(): bool {
    return $this->type === EvaluationTaskType::TASK_TYPE;
  }

  /**
   * ID of the test this task belongs to (if any).
   * @return string|NULL
   */
  public function getTestId() {
    return $this->testId;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::TASK_ID_KEY] = $this->id;
    $data[self::PRIORITY_KEY] = $this->priority;
    $data[self::FATAL_FAILURE_KEY] = $this->fatalFailure;
    $data[self::CMD_KEY] = [];
    $data[self::CMD_KEY][self::CMD_BIN_KEY] = $this->commandBinary;

    if (!empty($this->dependencies)) { $data[self::DEPENDENCIES] = $this->dependencies; }
    if (!empty($this->commandArguments)) { $data[self::CMD_KEY][self::CMD_ARGS_KEY] = $this->commandArguments; }
    if ($this->testId) { $data[self::TEST_ID_KEY] = $this->testId; }
    if ($this->type) { $data[self::TYPE_KEY] = $this->type; }
    return $data;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }
}
