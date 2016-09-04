<?php

namespace App\Helpers\JobConfig;


class TaskConfig {

  const TYPE_INITIATION = "initiation";
  const TYPE_EXECUTION = "execution";
  const TYPE_EVALUATION = "evaluation";

  private $data;
    
  public function __construct(array $data) {
    $this->data = $data;
  }

  /**
   * ID of the task itself
   * @return string
   */
  public function getId() {
    return $this->data["task-id"];
  }

  public function isInitiationTask() {
    return isset($this->data["type"]) && $this->data["type"] === self::TYPE_INITIATION;
  }

  public function isExecutionTask() {
    return isset($this->data["type"]) && $this->data["type"] === self::TYPE_EXECUTION;
  }

  public function getAsExecutionTask() {
    return new ExecutionTaskConfig($this->data);
  }

  public function isEvaluationTask() {
    return isset($this->data["type"]) && $this->data["type"] === self::TYPE_EVALUATION;
  }


  /**
   * ID of the test this task belongs to (if any)
   * @return string|NULL
   */
  public function getTestId() {
    return isset($this->data["test-id"]) ? $this->data["test-id"] : NULL;
  }

}
