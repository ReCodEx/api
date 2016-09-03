<?php

namespace App\Model\Helpers\EvaluationResults;

use App\Model\Helpers\JobConfig\Limits;

class StatsInterpretation {

  /** @var Stats Execution statistics */
  private $stats;

  /** @var Limits Configured limits */
  private $limits;

  public function __construct(Stats $stats, Limits $limits) {
    $this->stats = $stats;
    $this->limits = $limits;
  }

  public function doesMeetAllCriteria() {
    return $this->stats->doesMeetAllCriteria($this->limits);
  }

  /**
   * Checks if the execution time meets the limit
   * @return boolean
   */
  public function isTimeOK(): bool {
    return $this->stats->isTimeOK($this->limits->getTimeLimit());
  }

  /**
   * Checks if the allocated time meets the limit
   * @return boolean
   */
  public function isMemoryOK(): bool {
    return $this->stats->isMemoryOK($this->limits->getMemoryLimit());
  }


  public function getUsedMemoryRatio(): float {
    return floatval($stats->getUsedMemory() / floatval($limits->getMemoryLimit());
  }

  public function getUsedTimeRatio(): float {
    return floatval($stats->getUsedTime() / floatval($limits->getTimeLimit());
  }

}
