<?php

namespace App\Model\Helpers\JobConfig;


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
    return $this->data["type"] === self::TYPE_INITIATION;
  }

  public function isExecutionTask() {
    return $this->data["type"] === self::TYPE_EXECUTION;
  }

  public function isEvaluationTask() {
    return $this->data["type"] === self::TYPE_EVALUATION;
  }


  /**
   * ID of the test this task belongs to (if any)
   * @return string|NULL
   */
  public function getTestId() {
    return isset($this->data["test-id"]) ? $this->data["test-id"] : NULL;
  }

  /**
   * Get the configured limits for a specific hardware group.
   * @param  string $hardwareGroupId Hardware group ID
   * @return Limits Limits for the specified hardware group
   */
  public function getLimits($hardwareGroupId): array {
    foreach ($this->data["sandbox"]["limits"] as $limits) {
      if ($limits["hw-group-id"] === $hardwareGroupId) {
        return new Limits($limits["hw-group-id"]);
      }
    }

    return NULL;
  }

}
