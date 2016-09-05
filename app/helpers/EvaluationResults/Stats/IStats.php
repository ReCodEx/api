<?php

namespace App\Helpers\EvaluationResults;
use App\Helpers\JobConfig\Limits;

interface IStats {

  /**
   * Compares all the stats to the limits
   * @param  Limits $limits The configured limits
   * @return boolean
   */
  public function doesMeetAllCriteria(Limits $limits): bool;

  /**
   * @return float The time for which the process ran in seconds
   */
  public function getUsedTime(): float;

  /**
   * Compares the stats to the time limit.
   * @param  int     $secondsLimit Limiting amout of milliseconds
   * @return boolean
   */
  public function isTimeOK(float $secondsLimit): bool;

  /**
   * @return int The ammout of memory the process allocated
   */
  public function getUsedMemory(): int;

  /**
   * Compares the stats to the memory limit.
   * @param  int     $bytesLimit Limiting amout of bytes
   * @return boolean
   */
  public function isMemoryOK(int $bytesLimit): bool;

  /**
   * @return int The exit code fo the executable 
   */
  public function getExitCode(): int;

  /**
   * @return string The message from the evaluation system
   */
  public function getMessage(): string;

  /**
   * @return bool Whether the process was killed by the evaluation system or not
   */
  public function wasKilled(): bool;

}
