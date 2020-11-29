<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\Limits;
use App\Helpers\JobConfig\TestConfig;

/**
 * Implementation of test results. In this case, each test can have tasks
 * of multiple types: zero or many initiation tasks, zero on many execution
 * tasks and exactly one task of evaluation type.
 */
class TestResult
{
    public const STATUS_OK = "OK";
    public const STATUS_FAILED = "FAILED";
    public const STATUS_SKIPPED = "SKIPPED";

    public const SCORE_MIN = 0.0;
    public const SCORE_MAX = 1.0;

    /** @var TestConfig Test configuration */
    private $config;

    /** @var TaskResult[] Result of the execution task */
    private $executionResults;

    /** @var TaskResult Result of the evaluation task */
    private $evaluationResult;

    /** @var ISandboxResults[] List of results for each execution task in this test indexed with task identification */
    private $sandboxResultsList = [];

    /** @var string Status of the test */
    private $status;

    /** @var Limits[] Limits of the execution tasks of this test, indexed by task-id */
    private $limits;


    /**
     * Constructor
     * @param TestConfig $config Test configuration (contained tasks grouped by types, limits)
     * @param TaskResult[] $executionResults Results of execution tasks
     * @param TaskResult $evaluationResult Result of the one evaluation task
     * @param string $hardwareGroupId Identifier of hardware group on which was the test evaluated
     */
    public function __construct(
        TestConfig $config,
        array $executionResults,
        TaskResult $evaluationResult,
        string $hardwareGroupId
    ) {
        $this->config = $config;
        $this->executionResults = $executionResults;
        $this->evaluationResult = $evaluationResult;
        $this->limits = $config->getLimits($hardwareGroupId);

        // set the status based on the tasks runtime and their results
        $this->status = self::STATUS_OK;
        foreach ($this->executionResults as $result) {
            $this->status = self::calculateStatus($this->status, $result->getStatus());
            $this->sandboxResultsList[$result->getId()] = $result->getSandboxResults();
        }
        $this->status = self::calculateStatus($this->status, $evaluationResult->getStatus());

        // if the tested program exceeded its limits or scored zero points, we consider the test failed
        $isTestResultIncorrect = !$this->didExecutionMeetLimits() || $this->evaluationResult->getScore(
        ) === self::SCORE_MIN;

        if ($this->status === self::STATUS_OK && $isTestResultIncorrect) {
            $this->status = self::STATUS_FAILED;
        }
    }


    /**
     * Determines the status of the test based on the previously reduced status of the test
     * and the status of the next processed task result status.
     * @param string $curStatus Current status
     * @param string $newTaskStatus Next status
     * @return  string Status of the reduced test tasks statuses
     */
    public static function calculateStatus(string $curStatus, string $newTaskStatus): string
    {
        if ($curStatus === self::STATUS_OK) {
            return $newTaskStatus;
        } else {
            return $curStatus;
        }
    }

    /**
     * Get the ID of the test as it was defined in the config
     * @return string The ID
     */
    public function getId(): string
    {
        return $this->config->getId();
    }

    /**
     * Get the status of the whole test.
     * @return string The status, implementation specific
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Helper function for getting limits structure.
     * @param string $taskId
     * @return Limits|null
     */
    private function getLimits(string $taskId): ?Limits
    {
        if (array_key_exists($taskId, $this->limits)) {
            return $this->limits[$taskId];
        }
        return null;
    }

    /**
     * Gets array of execution tasks results
     * @return TaskResult[] List of results for all execution tasks in this test
     */
    public function getExecutionResults(): array
    {
        return $this->executionResults;
    }

    /**
     * Calculates the score for this test.
     * @return float The score between SCORE_MIN a SCORE_MAX
     */
    public function getScore(): float
    {
        if ($this->didExecutionMeetLimits() === false || $this->getStatus() !== self::STATUS_OK) {
            // even though the judge might say different, this test failed and the score is zero
            return self::SCORE_MIN;
        }

        return $this->evaluationResult->getScore();
    }

    /**
     * Checks the configuration against the actual performance.
     * @return boolean The result
     */
    public function didExecutionMeetLimits(): bool
    {
        $isStatusOk = true;
        foreach ($this->sandboxResultsList as $results) {
            if (!$results->isStatusOK()) {
                $isStatusOk = false;
            }
        }

        return $isStatusOk && $this->isWallTimeOK() && $this->isCpuTimeOK() && $this->isMemoryOK();
    }

    /**
     * Checks if the execution wall time of all tasks meets the limit
     * @return boolean The result
     */
    public function isWallTimeOK(): bool
    {
        $time = 0.0;
        foreach ($this->executionResults as $result) {
            if ($result->isSkipped() || $result->getSandboxResults()->isStatusTO()) {
                // skipped task is considered failed
                return false;
            }

            $limits = $this->getLimits($result->getId());
            if (
                $limits !== null && $limits->getWallTime() != 0.0 &&
                $result->getSandboxResults()->getUsedWallTime() > $limits->getWallTime()
            ) {
                // wall time limit was specified and used time exceeded it
                return false;
            }

            $time += $result->getSandboxResults()->getUsedWallTime();
        }

        $wallTimeLimit = $this->getUsedWallTimeLimit();
        if ($wallTimeLimit != 0.0 && $time > $wallTimeLimit) {
            // fail also if the summary of wall times is bigger than maximal wall time limit
            return false;
        }

        return true;
    }

    /**
     * Checks if the execution cpu time of all tasks meets the limit
     * @return boolean The result
     */
    public function isCpuTimeOK(): bool
    {
        $time = 0.0;
        foreach ($this->executionResults as $result) {
            if ($result->isSkipped() || $result->getSandboxResults()->isStatusTO()) {
                // skipped task is considered failed
                return false;
            }

            $limits = $this->getLimits($result->getId());
            if (
                $limits !== null && $limits->getTimeLimit() != 0.0 &&
                $result->getSandboxResults()->getUsedCpuTime() > $limits->getTimeLimit()
            ) {
                // cpu time limit was specified and used time exceeded it
                return false;
            }

            $time += $result->getSandboxResults()->getUsedCpuTime();
        }

        $cpuTimeLimit = $this->getUsedCpuTimeLimit();
        if ($cpuTimeLimit != 0.0 && $time > $cpuTimeLimit) {
            // fail also if the summary of cpu times is bigger than maximal cpu time limit
            return false;
        }

        return true;
    }

    /**
     * Checks if the execution memory of all tasks meets the limit
     * @return boolean The result
     */
    public function isMemoryOK(): bool
    {
        foreach ($this->executionResults as $result) {
            if ($result->isSkipped()) {
                // skipped task is considered failed
                return false;
            }

            $limits = $this->getLimits($result->getId());
            if (
                $limits !== null && $limits->getMemoryLimit() != 0 &&
                $result->getSandboxResults()->getUsedMemory() >= $limits->getMemoryLimit()
            ) {
                // memory limit was specified and used memory exceeded it
                return false;
            }
        }
        return true;
    }

    /**
     * Get the return code
     * @return int If all tasks are successful, return 0. If not, return first nonzero code returned.
     */
    public function getExitCode(): int
    {
        foreach ($this->sandboxResultsList as $results) {
            if ($results->getExitCode() !== 0) {
                return $results->getExitCode();
            }
        }
        return 0;
    }

    /**
     * Get the termination signal number or null, if no executed task was terminated.
     * @return int|null
     */
    public function getExitSignal(): ?int
    {
        foreach ($this->sandboxResultsList as $results) {
            if ($results->getExitSignal() !== null) {
                return $results->getExitSignal();
            }
        }
        return null;
    }

    /**
     * Get maximum memory limit of all execution tasks.
     * @return int in kilobytes
     */
    public function getUsedMemoryLimit(): int
    {
        $maxLimit = 0;
        foreach ($this->executionResults as $result) {
            $limits = $this->getLimits($result->getId());
            if ($limits && $limits->getMemoryLimit() > $maxLimit) {
                $maxLimit = $limits->getMemoryLimit();
            }
        }
        return $maxLimit;
    }

    /**
     * Get maximum used memory of all tasks.
     * @return int in kilobytes
     */
    public function getUsedMemory(): int
    {
        $maxMemory = 0;
        foreach ($this->sandboxResultsList as $results) {
            if ($results->getUsedMemory() > $maxMemory) {
                $maxMemory = $results->getUsedMemory();
            }
        }
        return $maxMemory;
    }

    /**
     * Get maximum wall time limit of all execution tasks.
     * @return float in seconds
     */
    public function getUsedWallTimeLimit(): float
    {
        $maxLimit = 0.0;
        foreach ($this->executionResults as $result) {
            $limits = $this->getLimits($result->getId());
            if ($limits && $limits->getWallTime() > $maxLimit) {
                $maxLimit = $limits->getWallTime();
            }
        }
        return $maxLimit;
    }

    /**
     * Get summary of used wall time of all tasks.
     * @return float in seconds
     */
    public function getUsedWallTime(): float
    {
        return array_reduce(
            $this->sandboxResultsList,
            function ($carry, ISandboxResults $results) {
                return $carry + $results->getUsedWallTime();
            },
            0.0
        );
    }

    /**
     * Get maximum cpu time limit of all execution tasks.
     * @return float in seconds
     */
    public function getUsedCpuTimeLimit(): float
    {
        $maxLimit = 0.0;
        foreach ($this->executionResults as $result) {
            $limits = $this->getLimits($result->getId());
            if ($limits && $limits->getTimeLimit() > $maxLimit) {
                $maxLimit = $limits->getTimeLimit();
            }
        }
        return $maxLimit;
    }

    /**
     * Get summary of used cpu time of all tasks.
     * @return float in seconds
     */
    public function getUsedCpuTime(): float
    {
        return array_reduce(
            $this->sandboxResultsList,
            function ($carry, ISandboxResults $results) {
                return $carry + $results->getUsedCpuTime();
            },
            0.0
        );
    }

    /**
     * Get first nonempty message, if any exists or empty string.
     * @return string The message
     */
    public function getMessage(): string
    {
        foreach ($this->sandboxResultsList as $results) {
            if (!empty($results->getMessage())) {
                return $results->getMessage();
            }
        }
        return "";
    }

    /**
     * Get judge stdout.
     * @return string Standard output of judge binary (evaluation task)
     */
    public function getJudgeStdout(): string
    {
        return $this->evaluationResult->getStdout();
    }

    /**
     * Get judge stderr.
     * @return string Error output of judge binary (evaluation task)
     */
    public function getJudgeStderr(): string
    {
        return $this->evaluationResult->getStderr();
    }
}
