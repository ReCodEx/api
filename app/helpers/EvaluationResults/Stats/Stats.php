<?php

namespace App\Helpers\EvaluationResults;

use App\Exception\ResultsLoadingException;
use App\Helpers\JobConfig\Limits;
use Nette\Utils\Json;

class Stats implements IStats {

  /** @var array Raw data of the stats */
  private $data;

  /** @var float Time used to complete the task */
  private $time;

  /** @var int Memory used by the exacutable */
  private $memory;

  /** @var int Exit code returned by the executed solution */
  private $exitcode;

  /** @var string Message from the evaluation worker */
  private $message;

  /** @var boolean Whether the process was killed by the evaluation system */
  private $killed;

  public function __construct(array $data) {
    $this->data = $data;

    if (!isset($data["exitcode"])) {
      throw new ResultsLoadingException("Sandbox results do not include the 'exitcode' field.");
    }

    $this->exitcode = $data["exitcode"];

    if (!isset($data["memory"])) {
      throw new ResultsLoadingException("Sandbox results do not include the 'memory' field.");
    }

    $this->memory = $data["memory"];

    if (!isset($data["time"])) {
      throw new ResultsLoadingException("Sandbox results do not include the 'time' field.");
    }

    $this->time = $data["time"];

    if (!isset($data["message"])) {
      throw new ResultsLoadingException("Sandbox results do not include the 'message' field.");
    }

    $this->message = $data["message"];

    if (!isset($data["killed"])) {
      throw new ResultsLoadingException("Sandbox results do not include the 'killed' field.");
    }

    $this->killed = $data["killed"];
  }

  /**
   * Compares all the stats to the limits
   * @param  Limits $limits The configured limits
   * @return boolean
   */
  public function doesMeetAllCriteria(Limits $limits): bool {
    return $this->isTimeOK($limits->getTimeLimit()) && $this->isMemoryOK($limits->getMemoryLimit());
  }

  /**
   * @return float The time for which the process ran in seconds
   */
  public function getUsedTime(): float {
    return $this->time;
  }

  /**
   * Compares the stats to the time limit.
   * @param  int     $secondsLimit Limiting amout of milliseconds
   * @return boolean
   */
  public function isTimeOK(float $secondsLimit): bool {
    return $this->getUsedTime() <= $secondsLimit;
  }

  /**
   * @return int The ammout of memory the process allocated
   */
  public function getUsedMemory(): int {
    return $this->memory;
  }

  /**
   * Compares the stats to the memory limit.
   * @param  int     $bytesLimit Limiting amout of bytes
   * @return boolean
   */
  public function isMemoryOK(int $bytesLimit): bool {
    return $this->getUsedMemory() <= $bytesLimit;
  }

  /**
   * @return int The exit code fo the executable 
   */
  public function getExitCode(): int {
    return $this->exitcode;
  }

  /**
   * @return string The message from the evaluation system
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * @return bool Whether the process was killed by the evaluation system or not
   */
  public function wasKilled(): bool {
    return $this->killed;
  }

  /**
   * Serialization of the data -> make a JSON of all the raw stats.
   */
  public function __toString() {
    return Json::encode($this->data);
  }

}
