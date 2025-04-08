<?php

namespace App\Helpers\JobConfig\Tasks;

use App\Helpers\JobConfig\Limits;
use App\Exceptions\JobConfigLoadingException;

/**
 * Holder for task which has execution type set.
 */
class ExecutionTaskType
{
    /** Execution task type value */
    public const TASK_TYPE = "execution";

    /** @var Task Execution task */
    private $task;

    /**
     * Checks and store execution task.
     * @param Task $task
     * @throws JobConfigLoadingException
     */
    public function __construct(Task $task)
    {
        if (!$task->isExecutionTask()) {
            throw new JobConfigLoadingException("Given task does not have type '" . self::TASK_TYPE . "'");
        }

        if (!$task->isSandboxedTask()) {
            throw new JobConfigLoadingException("Execution task has to have sandbox configuration defined");
        }

        $this->task = $task;
    }

    /**
     * Get execution task which was given and checked during construction.
     * @return Task
     */
    public function getTask(): Task
    {
        return $this->task;
    }

    /**
     * Get limits with given hardware group from internal task.
     * @param string $hwGroup hardware group identification
     * @return Limits|null limits structure
     */
    public function getLimits(string $hwGroup): ?Limits
    {
        return $this->task->getSandboxConfig()->getLimits($hwGroup);
    }
}
