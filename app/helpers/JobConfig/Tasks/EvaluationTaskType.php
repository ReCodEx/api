<?php

namespace App\Helpers\JobConfig\Tasks;

use App\Exceptions\JobConfigLoadingException;

/**
 * Holder for task which has evaluation type set.
 */
class EvaluationTaskType
{
    /** Evaluation task type value */
    public const TASK_TYPE = "evaluation";

    /** @var Task Evaluation task */
    private $task;

    /**
     * Checks and store evaluation task.
     * @param Task $task
     * @throws JobConfigLoadingException
     */
    public function __construct(Task $task)
    {
        if (!$task->isEvaluationTask()) {
            throw new JobConfigLoadingException("Given task does not have type '" . self::TASK_TYPE . "'");
        }

        $this->task = $task;
    }

    /**
     * Get evaluation task which was given and checked during construction.
     * @return Task
     */
    public function getTask(): Task
    {
        return $this->task;
    }
}
