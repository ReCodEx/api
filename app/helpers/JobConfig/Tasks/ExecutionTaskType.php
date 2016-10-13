<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Helpers\JobConfig\Limits;
use App\Exceptions\JobConfigLoadingException;


/**
 *
 */
class ExecutionTaskType {
  /** Execution task type value */
  const TASK_TYPE = "execution";

  /** @var ExternalTask Execution task */
  private $task;

  /**
   *
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
   *
   * @return TaskBase
   */
  public function getTask(): TaskBase {
    return $this->task;
  }

  /**
   *
   * @param string $hwGroup
   * @return Limits
   */
  public function getLimits(string $hwGroup): Limits {
    return $this->task->getSandboxConfig()->getLimits($hwGroup);
  }
}
