<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;

/**
 * Common evaluation results for all task types
 */
class TaskResult
{
    public const STATUS_OK = "OK";
    public const STATUS_FAILED = "FAILED";
    public const STATUS_SKIPPED = "SKIPPED";

    public const MAX_SCORE = 1.0;
    public const MIN_SCORE = 0.0;

    protected const TASK_ID_KEY = "task-id";
    protected const STATUS_KEY = "status";
    protected const OUTPUT_KEY = "output";
    protected const OUTPUT_STDOUT_KEY = "stdout";
    protected const OUTPUT_STDERR_KEY = "stderr";

    /** @var array Raw data */
    protected $data;

    /** @var string ID of the task */
    private $id;

    /** @var string Status of the task */
    private $status;

    /** @var string Output of the task to the stdout */
    protected $stdout = "";

    /** @var string Output of the task to the stderr */
    protected $stderr = "";

    /**
     * Constructor
     * @param array $data Raw result data
     * @throws ResultsLoadingException
     */
    public function __construct(array $data)
    {
        $this->data = $data;

        if (!isset($data[self::TASK_ID_KEY])) {
            throw new ResultsLoadingException(
                "Task result does include the required '" . self::TASK_ID_KEY . "' field."
            );
        }
        $this->id = $data[self::TASK_ID_KEY];

        if (!isset($data[self::STATUS_KEY])) {
            throw new ResultsLoadingException(
                "Task '{$this->id}' result does include the required '" . self::STATUS_KEY . "' field."
            );
        }
        $this->status = $data[self::STATUS_KEY];

        if (isset($data[self::OUTPUT_KEY])) {
            if (isset($data[self::OUTPUT_KEY][self::OUTPUT_STDOUT_KEY])) {
                $this->stdout = $data[self::OUTPUT_KEY][self::OUTPUT_STDOUT_KEY];
            }
            if (isset($data[self::OUTPUT_KEY][self::OUTPUT_STDERR_KEY])) {
                $this->stderr = $data[self::OUTPUT_KEY][self::OUTPUT_STDERR_KEY];
            }
        }
    }

    /**
     * Get unique task identifier
     * @return string ID of the task
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the status of the task
     * @return string The status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get parsed statistics of execution
     * @return ISandboxResults|null Statistics of the execution
     */
    public function getSandboxResults(): ?ISandboxResults
    {
        return null;
    }

    /**
     * Get standard and error output of the program (if enabled).
     * May be truncated by worker.
     * @return string concatenated stdout + stderr separated by a newline
     */
    public function getOutput(): string
    {
        $output = $this->stdout;
        if ($output && $this->stderr) {
            $output .= "\n";
        }
        $output .= $this->stderr;
        return $output;
    }

    /**
     * Return only the output made to stdout.
     * @return string
     */
    public function getStdout(): string
    {
        return $this->stdout;
    }

    /**
     * Return only the output made to stderr.
     * @return string
     */
    public function getStderr(): string
    {
        return $this->stderr;
    }

    /**
     * If the status of the task is 'OK'
     * @return boolean The result
     */
    public function isOK()
    {
        return $this->getStatus() === self::STATUS_OK;
    }

    /**
     * If the status of the task is 'SKIPPED'
     * @return boolean The result
     */
    public function isSkipped()
    {
        return $this->getStatus() === self::STATUS_SKIPPED;
    }

    /**
     * If the status of the task is 'FAILED'
     * @return boolean The result
     */
    public function hasFailed()
    {
        return $this->getStatus() === self::STATUS_FAILED;
    }

    /**
     * Get the score of this result
     * @return float The score
     */
    public function getScore(): float
    {
        return $this->isOK() ? self::MAX_SCORE : self::MIN_SCORE;
    }

    /**
     * Get as specific result for execution tasks
     * @return TaskResult The result
     * @throws ResultsLoadingException If cast is not possible
     */
    public function getAsExecutionTaskResult()
    {
        if ($this->isSkipped()) {
            return new SkippedTaskResult($this->getId());
        }
        return new ExecutionTaskResult($this->data);
    }

    /**
     * Get as specific result for evaluation tasks
     * @return TaskResult The result
     * @throws ResultsLoadingException If cast is not possible
     */
    public function getAsEvaluationTaskResult()
    {
        if ($this->isSkipped()) {
            return new SkippedTaskResult($this->getId());
        }
        return new EvaluationTaskResult($this->data);
    }
}
