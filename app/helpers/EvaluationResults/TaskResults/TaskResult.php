<?php

namespace App\Helpers\EvaluationResults;
use App\Exceptions\ResultsLoadingException;

class TaskResult {

  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";
  const STATUS_SKIPPED = "SKIPPED";

  const MAX_SCORE = 1.0;
  const MIN_SCORE = 0.0;
  
  /** @var array Raw data */
  protected $data;

  /** @var string ID of the task */
  private $id;

  /** @var string Status of the task */
  private $status;
  
  public function __construct(array $data) {
    $this->data = $data;

    if (!isset($data["task-id"])) {
      throw new ResultsLoadingException("Task result does include the required 'task-id' field.");
    }

    $this->id = $data["task-id"];

    if (!isset($data["status"])) {
      throw new ResultsLoadingException("Task '{$this->id}' result does include the required 'status' field.");
    }

    $this->status = $data["status"];
  }

  /**
   * @return string ID of the task
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Returns the status of the task
   * @return string The status
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * @return boolean The status of the task is 'OK' 
   */
  public function isOK() {
    return $this->getStatus() === self::STATUS_OK;
  }

  /**
   * @return boolean The status of the task is 'SKIPPED' 
   */
  public function isSkipped() {
    return $this->getStatus() === self::STATUS_SKIPPED;
  }

  /**
   * @return boolean The status of the task is 'FAILED' 
   */
  public function hasFailed() {
    return $this->getStatus() === self::STATUS_FAILED;
  }

  /**
   * Get the score of this result
   * @return float The score
   */
  public function getScore(): float {
    return $this->isOK() ? self::MAX_SCORE : self::MIN_SCORE;
  }

  /**
   * @throws ResultsLoadingException
   * @return ExecutionTaskResult
   */
  public function getAsExecutionTaskResult() {
    if ($this->isSkipped()) {
      return new SkippedTaskResult($this->getId());
    }
    return new ExecutionTaskResult($this->data);
  }

  /**
   * @throws ResultsLoadingException
   * @return EvaluationTaskResult
   */
  public function getAsEvaluationTaskResult() {
    if ($this->isSkipped()) {
      return new SkippedTaskResult($this->getId());
    }
    return new EvaluationTaskResult($this->data);
  }

}
