<?php

namespace App\Helpers\EvaluationResults;
use App\Exceptions\ResultsLoadingException;

class ExecutionTaskResult extends TaskResult {

  /** @var Stats Statistics of the execution */
  private $stats = NULL;

  public function __construct(array $data) {
    parent::__construct($data);

    if (!isset($data["sandbox_results"])) {
      throw new ResultsLoadingException("Execution task '{$this->getId()}' does not contain sandbox results.");
    }

    if (!is_array($data["sandbox_results"])) {
      throw new ResultsLoadingException("Execution task '{$this->getId()}' does not contain array of sandbox results.");
    }

    $this->stats = new Stats($data["sandbox_results"]);
  }

  /**
   * @return Stats Statistics of the execution
   */
  public function getStats(): Stats {
    return $this->stats;
  }

}
