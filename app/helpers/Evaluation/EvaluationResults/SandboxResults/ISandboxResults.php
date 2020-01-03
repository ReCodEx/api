<?php

namespace App\Helpers\EvaluationResults;

/**
 * Interface for accessing sandbox output of external task.
 */
interface ISandboxResults
{

    const EXIT_CODE_OK = 0;
    const EXIT_CODE_UNKNOWN = -1;

    const STATUS_OK = "OK";
    const STATUS_TO = "TO";

    /**
     * Get total amount of consumed wall time
     * @return float The wall time for which the process ran in seconds
     */
    public function getUsedWallTime(): float;

    /**
     * Get total amount of consumed cpu time
     * @return float The cpu time for which the process ran in seconds
     */
    public function getUsedCpuTime(): float;

    /**
     * Get total amount of consumed memory
     * @return int The ammout of memory the process allocated
     */
    public function getUsedMemory(): int;

    /**
     * Get exit code of examined program
     * @return int The exit code for the executable
     */
    public function getExitCode(): int;

    /**
     * Get human readable description of error or empty string
     * @return string The message from the evaluation system sandbox
     */
    public function getMessage(): string;

    /**
     * Whether the process was killed by the evaluation system or not
     * @return bool The result
     */
    public function wasKilled(): bool;

    /**
     * Get status of sandbox execution, one of the: OK, RE, SG, TO, XX
     * @return string
     */
    public function getStatus(): string;

    /**
     * True if status was in OK state.
     * @return bool
     */
    public function isStatusOK(): bool;

    /**
     * Determine whether execution was killed due to time-out.
     * @return bool
     */
    public function isStatusTO(): bool;
}
