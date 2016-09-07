<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;
use App\Helpers\JobConfig\Limits;
use Nette\Utils\Json;

class SkippedStats implements IStats {

  /**
   * Compares all the stats to the limits
   * @param  Limits $limits The configured limits
   * @return boolean
   */
  public function doesMeetAllCriteria(Limits $limits): bool {
    return FALSE;
  }

  /**
   * @return float The time for which the process ran in seconds
   */
  public function getUsedTime(): float {
    return 0;
  }

  /**
   * Compares the stats to the time limit.
   * @param  int     $secondsLimit Limiting amout of milliseconds
   * @return boolean
   */
  public function isTimeOK(float $secondsLimit): bool {
    return FALSE;
  }

  /**
   * @return int The ammout of memory the process allocated
   */
  public function getUsedMemory(): int {
    return 0;
  }

  /**
   * Compares the stats to the memory limit.
   * @param  int     $bytesLimit Limiting amout of bytes
   * @return boolean
   */
  public function isMemoryOK(int $bytesLimit): bool {
    return FALSE;
  }

  /**
   * @return int The exit code fo the executable 
   */
  public function getExitCode(): int {
    return 255;
  }

  /**
   * @return string The message from the evaluation system
   */
  public function getMessage(): string {
    return "";
  }

  /**
   * @return bool Whether the process was killed by the evaluation system or not
   */
  public function wasKilled(): bool {
    return FALSE;
  }

  /**
   * Serialization of the data -> make a JSON of all the raw stats.
   */
  public function __toString() {
    return "SKIPPED";
  }

}
