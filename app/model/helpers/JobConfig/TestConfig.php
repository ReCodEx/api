<?php

namespace App\Model\Helpers\JobConfig;

class TestConfig {

  /** @var string ID of the test */
  private $id;

  /** @var array The tasks which define this test */
  private $tasks;

  /** @var Task The task which defines the execution part of the test */
  private $executionTask;

  /** @var Task The task which defines the evaluation part of the test */
  private $evaluationTask;

  public function __construct(string $id, Limits $limits, array $tasks) {
    $this->id = $id;
    $this->limits = $limits;
    $this->tasks = $tasks;

    foreach ($tasks as $task) {
      if ($task->isExecutionTask()) {
        $this->executionTask = $task;
      } else if ($task->isEvaluationTask()) {
        $this->evaluationTask = $task;
      }
    }

    if ($this->executionTask === NULL || $this->evaluationTask === NULL) {
      // @todo 
    }
  }

  public function getLimits($hardwareGroupId) {
    return $this->getExecutionTask()->getLimits($hardwareGroupId);
  }

  public function getExecutionTask() {
    return $htis->executionTask;
  }

  public function getEvaluationTask() {
    return $htis->evaluationTask;
  }

}
