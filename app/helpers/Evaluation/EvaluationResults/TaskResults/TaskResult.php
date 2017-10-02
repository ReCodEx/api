<?php

namespace App\Helpers\EvaluationResults;
use App\Exceptions\ResultsLoadingException;

/**
 * Common evaluation results for all task types
 */
class TaskResult {
  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";
  const STATUS_SKIPPED = "SKIPPED";

  const MAX_SCORE = 1.0;
  const MIN_SCORE = 0.0;

  const TASK_ID_KEY = "task-id";
  const STATUS_KEY = "status";
  const OUTPUT_KEY = "output";

  /** @var array Raw data */
  protected $data;

  /** @var string ID of the task */
  private $id;

  /** @var string Status of the task */
  private $status;

  /** @var string Output of the task */
  protected $output;

  /**
   * Constructor
   * @param array $data Raw result data
   * @throws ResultsLoadingException
   */
  public function __construct(array $data) {
    $this->data = $data;

    if (!isset($data[self::TASK_ID_KEY])) {
      throw new ResultsLoadingException("Task result does include the required '" . self::TASK_ID_KEY ."' field.");
    }
    $this->id = $data[self::TASK_ID_KEY];

    if (!isset($data[self::STATUS_KEY])) {
      throw new ResultsLoadingException("Task '{$this->id}' result does include the required '" . self::STATUS_KEY . "' field.");
    }
    $this->status = $data[self::STATUS_KEY];

    if (isset($data[self::OUTPUT_KEY])) {
      $this->output = $data[self::OUTPUT_KEY];
    } else {
      $this->output = "";
    }
  }

  /**
   * Get unique task identifier
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
   * Get parsed statistics of execution
   * @return IStats|null Statistics of the execution
   */
  public function getStats(): ?IStats {
    return null;
  }

  /**
   * Get standard and error output of the program (if enabled).
   * May be truncated by worker.
   * @return string The standard output
   */
  public function getOutput(): string {
    return $this->output;
  }

  /**
   * If the status of the task is 'OK'
   * @return boolean The result
   */
  public function isOK() {
    return $this->getStatus() === self::STATUS_OK;
  }

  /**
   * If the status of the task is 'SKIPPED'
   * @return boolean The result
   */
  public function isSkipped() {
    return $this->getStatus() === self::STATUS_SKIPPED;
  }

  /**
   * If the status of the task is 'FAILED'
   * @return boolean The result
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
   * Get as specific result for execution tasks
   * @throws ResultsLoadingException If cast is not possible
   * @return TaskResult The result
   */
  public function getAsExecutionTaskResult() {
    if ($this->isSkipped()) {
      return new SkippedTaskResult($this->getId());
    }
    return new ExecutionTaskResult($this->data);
  }

  /**
   * Get as specific result for evaluation tasks
   * @throws ResultsLoadingException If cast is not possible
   * @return TaskResult The result
   */
  public function getAsEvaluationTaskResult() {
    if ($this->isSkipped()) {
      return new SkippedTaskResult($this->getId());
    }
    return new EvaluationTaskResult($this->data);
  }
}
