<?php

namespace App\Helpers\EvaluationResults;

/**
 * Task results for skipped tasks
 */
class SkippedTaskResult extends TaskResult
{
    /**
     * Constructor
     * @param string $id Task ID for which skipped results will be created
     */
    public function __construct(string $id)
    {
        parent::__construct(
            [
                self::TASK_ID_KEY => $id,
                self::STATUS_KEY => self::STATUS_SKIPPED
            ]
        );
    }

    public function getSandboxResults(): ?ISandboxResults
    {
        return new SkippedSandboxResults();
    }
}
