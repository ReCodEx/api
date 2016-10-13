<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\Limits;

/**
 * Interface for accessing sandbox output of external task.    
 */
interface IStats {

  /**
   * Compares all the stats to the limits
   * @param  Limits $limits The configured limits
   * @return boolean
   */
  public function doesMeetAllCriteria(Limits $limits): bool;

  /**
   * Get total amount of consumed time
   * @return float The time for which the process ran in seconds
   */
  public function getUsedTime(): float;

  /**
   * Compares the stats to the time limit
   * @param  int     $secondsLimit Limiting amout of milliseconds
   * @return boolean The result
   */
  public function isTimeOK(float $secondsLimit): bool;

  /**
   * Get total amount of consumed memory 
   * @return int The ammout of memory the process allocated
   */
  public function getUsedMemory(): int;

  /**
   * Compares the stats to the memory limit (in bytes)
   * @param  int     $bytesLimit Limiting amout of bytes
   * @return boolean The result
   */
  public function isMemoryOK(int $bytesLimit): bool;

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

}
