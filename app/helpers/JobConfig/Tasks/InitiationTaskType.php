<?php

namespace App\Helpers\JobConfig\Tasks;

use App\Exceptions\JobConfigLoadingException;

/**
 * Holder for task which has initiation type set.
 */
class InitiationTaskType
{
    /** Initiation task type value */
    public const TASK_TYPE = "initiation";

    /** @var Task Initiation task */
    private $task;

    /**
     * Checks and store initiation task.
     * @param Task $task
     * @throws JobConfigLoadingException
     */
    public function __construct(Task $task)
    {
        if (!$task->isInitiationTask()) {
            throw new JobConfigLoadingException("Given task does not have type '" . self::TASK_TYPE . "'");
        }

        $this->task = $task;
    }

    /**
     * Get initiation task which was given and checked during construction.
     * @return Task
     */
    public function getTask(): Task
    {
        return $this->task;
    }
}
