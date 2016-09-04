<?php

namespace App\Helpers\EvaluationResults;

class ExecutionTaskResult extends TaskResult {
    
  private $stats;

  public function __construct(array $data) {
    parent::__construct($data);
    $this->stats = new Stats($data["sandbox_results"]);
  }

  /**
   * Parses the judge output and yields the result
   * @return float The score
   */
  public function getScore(): float {
    if ($this->stats->hasJudgeOutput()) {
      return min(self::MAX_SCORE, max(self::MIN_SCORE, $this->stats->getJudgeOutput()));      
    }

    return parent::getScore();
  }

  public function getStats(): Stats {
    return $this->stats;
  }

}
