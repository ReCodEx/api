<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;
use App\Helpers\JobConfig\Limits;
use Nette\Utils\Json;

/**
 * Stats implementation for Isolate sandbox
 */
class Stats implements IStats {
  const EXITCODE_KEY = "exitcode";
  const MEMORY_KEY = "memory";
  const TIME_WALL_KEY = "wall-time";
  const MESSAGE_KEY = "message";
  const KILLED_KEY = "killed";
  const STATUS_KEY = "status";

  const STATUS_OK = "OK";

  /** @var array Raw data of the stats */
  private $data;

  /** @var float Time used to complete the task */
  private $time;

  /** @var int Memory used by the executable */
  private $memory;

  /** @var int Exit code returned by the executed solution */
  private $exitcode;

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
  public function __construct(array $data) {
    $this->data = $data;

    if (!isset($data[self::EXITCODE_KEY])) {
      throw new ResultsLoadingException("Sandbox results do not include the '" . self::EXITCODE_KEY . "' field.");
    }
    $this->exitcode = $data[self::EXITCODE_KEY];

    if (!isset($data[self::MEMORY_KEY])) {
      throw new ResultsLoadingException("Sandbox results do not include the '" . self::MEMORY_KEY . "' field.");
    }
    $this->memory = $data[self::MEMORY_KEY];

    if (!isset($data[self::TIME_WALL_KEY])) {
      throw new ResultsLoadingException("Sandbox results do not include the '" . self::TIME_WALL_KEY . "' field.");
    }
    $this->time = $data[self::TIME_WALL_KEY];

    if (!isset($data[self::MESSAGE_KEY])) {
      throw new ResultsLoadingException("Sandbox results do not include the '" . self::MESSAGE_KEY ."' field.");
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
   * Compares all the stats to the limits
   * @param  Limits $limits The configured limits
   * @return boolean The result
   */
  public function doesMeetAllCriteria(Limits $limits): bool {
    return $this->isTimeOK($limits->getWallTime()) && $this->isMemoryOK($limits->getMemoryLimit());
  }

  /**
   * Get time used by the program
   * @return float The time for which the process ran in seconds
   */
  public function getUsedTime(): float {
    return $this->time;
  }

  /**
   * Compares the stats to the time limit.
   * @param float|int $secondsLimit Limiting amount of milliseconds
   * @return bool The result
   */
  public function isTimeOK(float $secondsLimit): bool {
    if ($secondsLimit === 0.0) {
      return true;
    }
    return $this->getUsedTime() <= $secondsLimit;
  }

  /**
   * Get memory used by the program
   * @return int The amount of memory the process allocated
   */
  public function getUsedMemory(): int {
    return $this->memory;
  }

  /**
   * Compares the stats to the memory limit.
   * @param  int     $bytesLimit Limiting amount of bytes
   * @return boolean The result
   */
  public function isMemoryOK(int $bytesLimit): bool {
    if ($bytesLimit === 0) {
      return true;
    }
    return $this->getUsedMemory() < $bytesLimit;
  }

  /**
   * Get code returned by the program
   * @return int The exit code fo the executable
   */
  public function getExitCode(): int {
    if ($this->status !== self::STATUS_OK && $this->exitcode === self::EXIT_CODE_OK) {
      return self::EXIT_CODE_UNKNOWN;
    }

    return $this->exitcode;
  }

  /**
   * Get human readable message
   * @return string The message from the evaluation system
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Whether the process was killed by the evaluation system or not
   * @return bool The result
   */
  public function wasKilled(): bool {
    return $this->killed;
  }

  /**
   * Serialization of the data -> make a JSON of all the raw stats.
   * @return string Serialized content
   */
  public function __toString() {
    return Json::encode($this->data);
  }
}
