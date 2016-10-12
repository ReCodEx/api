<?php

namespace App\Helpers\JobConfig\Tasks;


class EvaluationTaskType {
  const TASK_TYPE = "evaluation";

  /** @var TaskBase */
  private $task;

  public function __construct(TaskBase $task) {
    $this->task = $task;
  }

  public function getTask() {
    return $this->task;
  }
}
