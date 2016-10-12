<?php

namespace App\Helpers\JobConfig\Tasks;


class InitiationTaskType {
  const TASK_TYPE = "initiation";

  /** @var TaskBase */
  private $task;

  public function __construct(TaskBase $task) {
    $this->task = $task;
  }

  public function getTask() {
    return $this->task;
  }
}
