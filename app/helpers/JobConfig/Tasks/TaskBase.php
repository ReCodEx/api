<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;


/**
 *
 */
abstract class TaskBase {

  /**  */
  const TASK_ID_KEY = "task-id";
  /**  */
  const PRIORITY_KEY = "priority";
  /**  */
  const FATAL_FAILURE_KEY = "fatal-failure";
  /**  */
  const DEPENDENCIES = "dependencies";
  /**  */
  const CMD_KEY = "cmd";
  /**  */
  const CMD_BIN_KEY = "bin";
  /**  */
  const CMD_ARGS_KEY = "args";
  /**  */
  const TEST_ID_KEY = "test-id";
  /**  */
  const TYPE_KEY = "type";

  /** @var string Task ID */
  protected $id;
  /** @var integer Priority of task, higher number means higher priority */
  protected $priority;
  /** @var boolean If true execution of whole job will be stopped on failure */
  protected $fatalFailure;
  /** @var array */
  protected $dependencies = [];
  /** @var string */
  protected $commandBinary;
  /** @var array */
  protected $commandArguments = [];
  /** @var string Type of the task */
  protected $type = NULL;
  /** @var string ID of the test to which this task corresponds */
  protected $testId = NULL;
  /** @var array Additional data */
  protected $data = [];

  /**
   *
   * @param array $data
   * @throws JobConfigLoadingException
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
   * ID of the task itself
   * @return string
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   *
   * @return int
   */
  public function getPriority(): int {
    return $this->priority;
  }

  /**
   *
   * @return bool
   */
  public function getFatalFailure(): bool {
    return $this->fatalFailure;
  }

  /**
   *
   * @return array
   */
  public function getDependencies(): array {
    return $this->dependencies;
  }

  /**
   *
   * @return string
   */
  public function getCommandBinary(): string {
    return $this->commandBinary;
  }

  /**
   *
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
   *
   * @return bool
   */
  public function isInitiationTask(): bool {
    return $this->type === InitiationTaskType::TASK_TYPE;
  }

  /**
   *
   * @return bool
   */
  public function isExecutionTask(): bool {
    return $this->type === ExecutionTaskType::TASK_TYPE;
  }

  /**
   *
   * @return bool
   */
  public function isEvaluationTask(): bool {
    return $this->type === EvaluationTaskType::TASK_TYPE;
  }

  /**
   * ID of the test this task belongs to (if any)
   * @return string|NULL
   */
  public function getTestId() {
    return $this->testId;
  }

  /**
   *
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
   * Serialize the config
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }
}
