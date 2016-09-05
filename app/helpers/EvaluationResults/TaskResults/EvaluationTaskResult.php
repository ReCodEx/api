<?php

namespace App\Helpers\EvaluationResults;
use App\Exception\ResultsLoadingException;

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
      $score = floatval(strtok($this->judgeOutput, " "));
      $this->score = min(TaskResult::MAX_SCORE, max(TaskResult::MIN_SCORE, $score));
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
