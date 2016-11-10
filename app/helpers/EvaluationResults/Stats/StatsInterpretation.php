<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\Limits;

/**
 * Compare statistics with limits for a task
 */
class StatsInterpretation {

  /** @var Stats Execution statistics */
  private $stats;

  /** @var Limits Configured limits */
  private $limits;

  /**
   * Constructor
   * @param Stats $stats    Output data from sandbox evaluation
   * @param Limits $limits  Restrictions for current task evaluation
   */
  public function __construct(IStats $stats, Limits $limits) {
    $this->stats = $stats;
    $this->limits = $limits;
  }

  /**
   * Whenever the evaluation meets all required limits
   * @return boolean The result
   */
  public function doesMeetAllCriteria() {
    return $this->stats->doesMeetAllCriteria($this->limits);
  }

  /**
   * Checks if the execution time meets the limit
   * @return boolean The result
   */
  public function isTimeOK(): bool {
    return $this->stats->isTimeOK($this->limits->getTimeLimit());
  }

  /**
   * Checks if the allocated time meets the limit
   * @return boolean The result
   */
  public function isMemoryOK(): bool {
    return $this->stats->isMemoryOK($this->limits->getMemoryLimit());
  }

  /**
   * Get percentage of used memory
   * @return float Ratio between 0.0 and 1.0
   */
  public function getUsedMemoryRatio(): float {
    return floatval($this->stats->getUsedMemory()) / floatval($this->limits->getMemoryLimit());
  }

  /**
   * Get percentage of used time
   * @return float Ratio between 0.0 and 1.0
   */
  public function getUsedTimeRatio(): float {
    return floatval($this->stats->getUsedTime()) / floatval($this->limits->getTimeLimit());
  }

}
