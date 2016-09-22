<?php

namespace App\Helpers\EvaluationResults;
use App\Exceptions\ResultsLoadingException;
use Nette\Utils\Validators;

class EvaluationTaskResult extends TaskResult {

  /** @var string The output of the judge */
  private $judgeOutput = "";

  /** @var float Explicit score from the results */
  private $score = NULL;

  public function __construct(array $data) {
    parent::__construct($data);

    // judge output is optional and only the first token is interpreted as float value between 0 and 1
    if (isset($data["judge_output"]) && !empty($data["judge_output"])) {
      $this->judgeOutput = $data["judge_output"];
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

  public function getJudgeOutput(): string {
    return $this->judgeOutput;
  }

}
