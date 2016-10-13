<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Exceptions\JobConfigLoadingException;


/**
 * Holder for task which has initiation type set.
 */
class InitiationTaskType {
  /** Initiation task type value */
  const TASK_TYPE = "initiation";

  /** @var TaskBase Initiation task */
  private $task;

  /**
   * Checks and store initiation task.
   * @param TaskBase $task
   * @throws JobConfigLoadingException
   */
  public function __construct(TaskBase $task) {
    if (!$task->isInitiationTask()) {
      throw new JobConfigLoadingException("Given task does not have type '" . self::TASK_TYPE . "'");
    }

    $this->task = $task;
  }

  /**
   * Get initiation task which was given and checked during construction.
   * @return TaskBase
   */
  public function getTask(): TaskBase {
    return $this->task;
  }

}
