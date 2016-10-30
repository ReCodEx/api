<?php

namespace App\Helpers\EvaluationResults;
use App\Exceptions\ResultsLoadingException;
use Nette\Utils\Validators;

/**
 * Results of evaluation tasks (judges)
 */
class EvaluationTaskResult extends TaskResult {
  const JUDGE_OUTPUT_KEY = "judge_output";

  /** @var string The output of the judge */
  private $judgeOutput = "";

  /** @var float Explicit score from the results */
  private $score = NULL;

  /**
   * Constructor
   * @param array $data Raw result data
   */
  public function __construct(array $data) {
    parent::__construct($data);

    // judge output is optional and only the first token is interpreted as float value between 0 and 1
    if (isset($data[self::JUDGE_OUTPUT_KEY]) && !empty($data[self::JUDGE_OUTPUT_KEY])) {
      $this->judgeOutput = $data[self::JUDGE_OUTPUT_KEY];
      $token = strtok($this->judgeOutput, " ");
      if (Validators::isNumeric($token) === FALSE) {
        throw new ResultsLoadingException("First token of the judge's output for task '{$this->getId()}' cannot be interpreted as number.");
      }

      $this->score = min(TaskResult::MAX_SCORE, max(TaskResult::MIN_SCORE, floatval($token)));
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

  /**
   * Raw standard output of judge execution
   * @return string The output
   */
  public function getJudgeOutput(): string {
    return $this->judgeOutput;
  }

}
