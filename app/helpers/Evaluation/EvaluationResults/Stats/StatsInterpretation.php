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
   * Checks if the execution time meets the limit
   * @return boolean The result
   */
  public function isTimeOK(): bool {
    return $this->limits === null || $this->stats->isTimeOK($this->limits->getWallTime());
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
   * Get percentage of used time
   * @return float Ratio between 0.0 and 1.0
   */
  public function getUsedTimeRatio(): float {
    if ($this->limits === null || $this->limits->getWallTime() === 0.0) {
      return 0;
    }
    return floatval($this->stats->getUsedTime()) / floatval($this->limits->getWallTime());
  }

  /**
   * Get used time in seconds.
   * @return float
   */
  public function getUsedTime(): float {
    return $this->stats->getUsedTime();
  }

}
