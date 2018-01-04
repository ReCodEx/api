<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\Limits;

/**
 * Compare statistics with limits for a task
 */
class StatsInterpretation {

  /** @var IStats Execution statistics */
  private $stats;

  /** @var Limits Configured limits */
  private $limits;

  /**
   * Constructor
   * @param IStats $stats Output data from sandbox evaluation
   * @param Limits|null $limits Restrictions for current task evaluation
   */
  public function __construct(IStats $stats, ?Limits $limits) {
    $this->stats = $stats;
    $this->limits = $limits;
  }

  /**
   * Whenever the evaluation meets all required limits
   * @return boolean The result
   */
  public function doesMeetAllCriteria() {
    return $this->limits === null || $this->stats->doesMeetAllCriteria($this->limits);
  }

  /**
   * Checks if the execution wall time meets the limit
   * @return boolean The result
   */
  public function isWallTimeOK(): bool {
    return $this->limits === null || $this->stats->isWallTimeOK($this->limits->getWallTime());
  }

  /**
   * Checks if the execution cpu time meets the limit
   * @return boolean The result
   */
  public function isCpuTimeOK(): bool {
    return $this->limits === null || $this->stats->isCpuTimeOK($this->limits->getTimeLimit());
  }

  /**
   * Checks if the allocated time meets the limit
   * @return boolean The result
   */
  public function isMemoryOK(): bool {
    return $this->limits === null || $this->stats->isMemoryOK($this->limits->getMemoryLimit());
  }

  /**
   * Get percentage of used memory
   * @return float Ratio between 0.0 and 1.0
   */
  public function getUsedMemoryRatio(): float {
    if ($this->limits === null || $this->limits->getMemoryLimit() === 0) {
      return 0;
    }
    return floatval($this->stats->getUsedMemory()) / floatval($this->limits->getMemoryLimit());
  }

  /**
   * Get used memory in kilobytes.
   * @return int
   */
  public function getUsedMemory(): int {
    return $this->stats->getUsedMemory();
  }

  /**
   * Get percentage of used wall time
   * @return float Ratio between 0.0 and 1.0
   */
  public function getUsedWallTimeRatio(): float {
    if ($this->limits === null || $this->limits->getWallTime() === 0.0) {
      return 0;
    }
    return floatval($this->stats->getUsedWallTime()) / floatval($this->limits->getWallTime());
  }

  /**
   * Get used wall time in seconds.
   * @return float
   */
  public function getUsedWallTime(): float {
    return $this->stats->getUsedWallTime();
  }

  /**
   * Get percentage of used cpu time
   * @return float Ratio between 0.0 and 1.0
   */
  public function getUsedCpuTimeRatio(): float {
    if ($this->limits === null || $this->limits->getTimeLimit() === 0.0) {
      return 0;
    }
    return floatval($this->stats->getUsedCpuTime()) / floatval($this->limits->getTimeLimit());
  }

  /**
   * Get used cpu time in seconds.
   * @return float
   */
  public function getUsedCpuTime(): float {
    return $this->stats->getUsedCpuTime();
  }

}
