<?php

namespace App\Helpers\JobConfig;

class TestConfig {

  /** @var string ID of the test */
  private $id;

  /** @var array The tasks which define this test */
  private $tasks;

  /** @var Task The task which defines the execution part of the test */
  private $executionTask;

  /** @var Task The task which defines the evaluation part of the test */
  private $evaluationTask;

  public function __construct(string $id, array $tasks) {
    $this->id = $id;
    $this->tasks = $tasks;

    foreach ($tasks as $task) {
      if ($task->isExecutionTask()) {
        $this->executionTask = $task->getAsExecutionTask();
      } else if ($task->isEvaluationTask()) {
        $this->evaluationTask = $task;
      }
    }

    if ($this->executionTask === NULL || $this->evaluationTask === NULL) {
      // @todo 
    }
  }

  public function getId() {
    return $this->id;
  }

  public function getLimits($hardwareGroupId) {
    return $this->getExecutionTask()->getLimits($hardwareGroupId);
  }

  public function getExecutionTask() {
    return $this->executionTask;
  }

  public function getEvaluationTask() {
    return $this->evaluationTask;
  }

}
