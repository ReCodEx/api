<?php

namespace App\Helpers\EvaluationResults;

/**
 * Default stats for skipped tasks (the execution was not performed due to previous errors)
 */
class SkippedSandboxResults implements ISandboxResults
{
    /**
     * Get total amount of consumed time
     * @return float The time for which the process ran in seconds
     */
    public function getUsedWallTime(): float
    {
        return 0;
    }

    /**
     * Get total amount of consumed cpu time
     * @return float The cpu time for which the process ran in seconds
     */
    public function getUsedCpuTime(): float
    {
        return 0;
    }

    /**
     * Get total amount of consumed memory
     * @return int The amount of memory the process allocated
     */
    public function getUsedMemory(): int
    {
        return 0;
    }

    /**
     * Get exit code of examined program
     * @return int The exit code for the executable
     */
    public function getExitCode(): int
    {
        return self::EXIT_CODE_UNKNOWN;
    }

    /**
     * Get exit signal that examined program
     * @return int|null The signal number or null if the program exited normally
     */
    public function getExitSignal(): ?int
    {
        return null;
    }

    /**
     * Get human readable description of error or empty string
     * @return string The message from the evaluation system sandbox
     */
    public function getMessage(): string
    {
        return "";
    }

    /**
     * Whether the process was killed by the evaluation system or not
     * @return bool The result
     */
    public function wasKilled(): bool
    {
        return false;
    }

    /**
     * Serialization of the data -> make a JSON of all the raw stats.
     * @return string Skipped task identifier "SKIPPED"
     */
    public function __toString()
    {
        return "SKIPPED";
    }

    /**
     * Get status of sandbox execution, one of the: OK, RE, SG, TO, XX
     * @return string
     */
    public function getStatus(): string
    {
        return self::STATUS_OK;
    }

    /**
     * True if status was in OK state.
     * @return bool
     */
    public function isStatusOK(): bool
    {
        return false;
    }

    /**
     * Determine whether execution was killed due to time-out.
     * @return bool
     */
    public function isStatusTO(): bool
    {
        return false;
    }
}
