<?php

namespace App\Helpers\JobConfig\Tasks;


class ExecutionTaskType {
  const TASK_TYPE = "execution";

  /** @var TaskBase */
  private $task;

  public function __construct(TaskBase $task) {
    $this->task = $task; // TODO: check for external task
  }

  public function getTask() {
    return $this->task;
  }
}
