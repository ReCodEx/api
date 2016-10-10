<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

abstract class TaskConfig {

  const TYPE_INITIATION = "initiation";
  const TYPE_EXECUTION = "execution";
  const TYPE_EVALUATION = "evaluation";

  /** @var string Task ID */
  private $id;

  /** @var string Type of the task */
  private $type;

  /** @var string ID of the test to which this task corresponds */
  private $testId;

  /** @var array Raw data */
  private $data;

  public function __construct(array $data) {
    if (!isset($data["task-id"])) {
      throw new JobConfigLoadingException("Task configuration does not contain required 'task-id' field.");
    }

    $this->data = $data;
    $this->id = $data["task-id"];
    $this->type = isset($data["type"]) ? $data["type"] : NULL;
    $this->testId = isset($data["test-id"]) ? $data["test-id"] : NULL;
  }

  /**
   * ID of the task itself
   * @return string
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Type of the task
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  public function isInitiationTask() {
    return $this->type === self::TYPE_INITIATION;
  }

  public function isExecutionTask() {
    return $this->type === self::TYPE_EXECUTION;
  }

  public function getAsExecutionTask() {
    if ($this instanceof ExecutionTaskConfig) {
        return $this;
    }
    return new ExecutionTaskConfig($this->toArray());
  }

  public function isEvaluationTask() {
    return $this->type === self::TYPE_EVALUATION;
  }

  /**
   * ID of the test this task belongs to (if any)
   * @return string|NULL
   */
  public function getTestId() {
    return $this->testId;
  }

  public function toArray() {
    return $this->data;
  }

  /**
   * Serialize the config
   * @return string
   */
  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
