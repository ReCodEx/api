<?php

namespace App\Model\Helpers\EvaluationResults;

class TaskResult {

  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";
  const STATUS_SKIPPED = "SKIPPED";

  const MAX_SCORE = 1.0;
  const MIN_SCORE = 0.0;
  
  protected $data;
  
  public function __construct(array $data) {
    $this->data = $data;
  }

  /**
   * Returns the status of the task
   * @return string
   */
  public function getStatus() {
    return $this->data["status"];
  }

  public function isOK() {
    return $this->getStatus() === self::STATUS_OK;
  }

  /**
   * Get the score of this result
   * @return [type] [description]
   */
  public function getScore(): float {
    return $this->isOK() ? self::MAX_SCORE : self::MIN_SCORE;
  }

}
