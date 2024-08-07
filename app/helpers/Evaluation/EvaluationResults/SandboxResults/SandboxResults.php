<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;
use Nette\Utils\Json;

/**
 * Stats implementation for Isolate sandbox
 */
class SandboxResults implements ISandboxResults
{
    private const EXITCODE_KEY = "exitcode";
    private const EXITSIGNAL_KEY = "exitsig";
    private const MEMORY_KEY = "memory";
    private const CPU_TIME_KEY = "time";
    private const WALL_TIME_KEY = "wall-time";
    private const MESSAGE_KEY = "message";
    private const KILLED_KEY = "killed";
    private const STATUS_KEY = "status";


    /** @var array Raw data of the stats */
    private $data;

    /** @var float Wall time used to complete the task */
    private $wallTime;

    /** @var float Cpu time used to complete the task */
    private $cpuTime;

    /** @var int Memory used by the executable */
    private $memory;

    /** @var int Exit code returned by the executed solution */
    private $exitcode;

    /** @var int Exit signal number or null if terminated regularly */
    private $exitsignal;

    /** @var string Message from the evaluation worker */
    private $message;

    /** @var boolean Whether the process was killed by the evaluation system */
    private $killed;

    /** @var string Status in which process ended */
    private $status;

    /**
     * Constructor
     * @param array $data Raw data from basic parsing of sandbox output
     * @throws ResultsLoadingException
     */
    public function __construct(array $data)
    {
        $this->data = $data;

        if (!isset($data[self::EXITCODE_KEY])) {
            throw new ResultsLoadingException("Sandbox results do not include the '" . self::EXITCODE_KEY . "' field.");
        }
        $this->exitcode = $data[self::EXITCODE_KEY];

        $this->exitsignal = $data[self::EXITSIGNAL_KEY] ?? null;

        if (!isset($data[self::MEMORY_KEY])) {
            throw new ResultsLoadingException("Sandbox results do not include the '" . self::MEMORY_KEY . "' field.");
        }
        $this->memory = $data[self::MEMORY_KEY];

        if (!isset($data[self::WALL_TIME_KEY])) {
            throw new ResultsLoadingException(
                "Sandbox results do not include the '" . self::WALL_TIME_KEY . "' field."
            );
        }
        $this->wallTime = $data[self::WALL_TIME_KEY];

        if (!isset($data[self::CPU_TIME_KEY])) {
            throw new ResultsLoadingException("Sandbox results do not include the '" . self::CPU_TIME_KEY . "' field.");
        }
        $this->cpuTime = $data[self::CPU_TIME_KEY];

        if (!isset($data[self::MESSAGE_KEY])) {
            throw new ResultsLoadingException("Sandbox results do not include the '" . self::MESSAGE_KEY . "' field.");
        }
        $this->message = $data[self::MESSAGE_KEY];

        if (!isset($data[self::KILLED_KEY])) {
            throw new ResultsLoadingException("Sandbox results do not include the '" . self::KILLED_KEY . "' field.");
        }
        $this->killed = $data[self::KILLED_KEY];

        if (!isset($data[self::STATUS_KEY])) {
            throw new ResultsLoadingException("Sandbox results do not include the '" . self::STATUS_KEY . "' field.");
        }
        $this->status = $data[self::STATUS_KEY];
    }

    /**
     * Get wall time used by the program
     * @return float The time for which the process ran in seconds
     */
    public function getUsedWallTime(): float
    {
        return $this->wallTime;
    }

    /**
     * Get cpu time used by the program
     * @return float The cpu time for which the process ran in seconds
     */
    public function getUsedCpuTime(): float
    {
        return $this->cpuTime;
    }

    /**
     * Get memory used by the program
     * @return int The amount of memory the process allocated
     */
    public function getUsedMemory(): int
    {
        return $this->memory;
    }

    /**
     * Get code returned by the program
     * @return int The exit code fo the executable
     */
    public function getExitCode(): int
    {
        if (
            $this->status !== self::STATUS_OK && $this->status !== self::STATUS_RE
            && $this->exitcode === self::EXIT_CODE_OK
        ) {
            return self::EXIT_CODE_UNKNOWN;
        }

        return $this->exitcode;
    }

    /**
     * Get the signal number that terminated the task.
     * @return int|null signal number or null if no signal was captured
     */
    public function getExitSignal(): ?int
    {
        return $this->exitsignal;
    }

    /**
     * Get human readable message
     * @return string The message from the evaluation system
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Whether the process was killed by the evaluation system or not
     * @return bool The result
     */
    public function wasKilled(): bool
    {
        return $this->killed;
    }

    /**
     * Get status of sandbox execution, one of the: OK, RE, SG, TO, XX
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * True if status was in OK state.
     * @return bool
     */
    public function isStatusOK(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /**
     * Determine whether execution was killed due to time-out.
     * @return bool
     */
    public function isStatusTO(): bool
    {
        return $this->status === self::STATUS_TO;
    }

    /**
     * Serialization of the data -> make a JSON of all the raw stats.
     * @return string Serialized content
     * @throws \Nette\Utils\JsonException
     */
    public function __toString()
    {
        return Json::encode($this->data);
    }
}
