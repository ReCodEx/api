<?php

namespace App\Helpers\EvaluationResults;
use App\Exceptions\ResultsLoadingException;
use Nette\Utils\Validators;

/**
 * Results of evaluation tasks (judges)
 */
class EvaluationTaskResult extends TaskResult {

  /** @var float|null Explicit score from the results */
  private $score = null;

  /**
   * Constructor
   * @param array $data Raw result data
   * @throws ResultsLoadingException
   */
  public function __construct(array $data) {
    parent::__construct($data);

    // judge output is optional and only the first token is interpreted as float value between 0 and 1
    if (!empty($this->output)) {
      $token = strtok($this->output, " ");
      if (Validators::isNumeric($token) === true) {
        $this->score = min(TaskResult::MAX_SCORE, max(TaskResult::MIN_SCORE, floatval($token)));
      }
    }
  }

  /**
   * Parses the judge output and yields the result
   * @return float The score
   */
  public function getScore(): float {
    if ($this->score !== null) {
      return $this->score;
    }
    return parent::getScore();
  }
}
