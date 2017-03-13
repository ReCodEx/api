<?php

namespace App\Helpers\EvaluationResults;
use App\Exceptions\ResultsLoadingException;
use Nette\Utils\Validators;

/**
 * Results of evaluation tasks (judges)
 */
class EvaluationTaskResult extends TaskResult {

  /** @var float|NULL Explicit score from the results */
  private $score = NULL;

  /**
   * Constructor
   * @param array $data Raw result data
   */
  public function __construct(array $data) {
    parent::__construct($data);

    // judge output is optional and only the first token is interpreted as float value between 0 and 1
    if (!empty($this->output)) {
      $token = strtok($this->output, " ");
      if (Validators::isNumeric($token) === FALSE) {
        throw new ResultsLoadingException("First token of the judge's output for task '{$this->getId()}' cannot be interpreted as number.");
      }

      $this->score = min(TaskResult::MAX_SCORE, max(TaskResult::MIN_SCORE, floatval($token)));
    } else {
      $this->score = TaskResult::MAX_SCORE;
    }
  }

  /**
   * Parses the judge output and yields the result
   * @return float The score
   */
  public function getScore(): float {
    if ($this->score !== NULL) {
      return $this->score;
    }
    return parent::getScore();
  }
}
