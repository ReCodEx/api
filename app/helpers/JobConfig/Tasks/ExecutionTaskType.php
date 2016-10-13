<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Helpers\JobConfig\Limits;
use App\Exceptions\JobConfigLoadingException;


/**
 * Holder for task which has execution type set.
 */
class ExecutionTaskType {
  /** Execution task type value */
  const TASK_TYPE = "execution";

  /** @var ExternalTask Execution task */
  private $task;

  /**
   * Checks and store execution task.
   * @param TaskBase $task
   * @throws JobConfigLoadingException
   */
  public function __construct(TaskBase $task) {
    if (!$task->isExecutionTask()) {
      throw new JobConfigLoadingException("Given task does not have type '" . self::TASK_TYPE . "'");
    }

    if (!$task instanceof ExternalTask) {
      throw new JobConfigLoadingException("Execution task has to be ExternalTask type");
    }

    $this->task = $task;
  }

  /**
   * Get execution task which was given and checked during construction.
   * @return TaskBase
   */
  public function getTask(): TaskBase {
    return $this->task;
  }

  /**
   * Get limits with given hardware group from internal task.
   * @param string $hwGroup hardware group identification
   * @return Limits limits structure
   */
  public function getLimits(string $hwGroup): Limits {
    return $this->task->getSandboxConfig()->getLimits($hwGroup);
  }

}
