<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Exceptions\JobConfigLoadingException;


class InitiationTaskType {
  const TASK_TYPE = "initiation";

  /** @var TaskBase */
  private $task;

  public function __construct(TaskBase $task) {
    if (!$task->isInitiationTask()) {
      throw new JobConfigLoadingException("Given task does not have type '" . self::TASK_TYPE . "'");
    }

    $this->task = $task;
  }

  public function getTask(): TaskBase {
    return $this->task;
  }
}
