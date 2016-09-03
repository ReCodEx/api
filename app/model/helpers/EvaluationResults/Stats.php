<?php

namespace App\Model\Helpers\EvaluationResults;

use App\Model\Helpers\JobConfig\Limits;
use Nette\Utils\Json;

class Stats {

  /** @var array Raw data of the stats */
  private $data;

  public function __construct(array $data) {
    $this->data = $data;
  }

  /**
   * Compares all the stats to the limits
   * @param  Limits $limits The configured limits
   * @return boolean
   */
  public function doesMeetAllLimits(Limits $limits): bool {
    return $this->isTimeOK($limits->getTimeLimit()) && $this->isMemmoryOK($limits->getMemmoryLimit());
  }

  /**
   * Looks whether the output of the judge is available among the data
   * @return boolean
   */
  public function hasJudgeOutput(): bool {
    return isset($this->data["judge_output"]) && !empty($this->data["judge_output"]);
  }

  /**
   * Returns the value of the judge output
   * @return float
   */
  public function getJudgeOutput(): float {
    return floatval(strtok($this->data["judge_output"], " "));
  }

  public function getUsedTime(): float {
    return $this->data["time"];
  }

  /**
   * Compares the stats to the time limit.
   * @param  int     $secondsLimit Limiting amout of milliseconds
   * @return boolean
   */
  public function isTimeOK(float $secondsLimit): bool {
    return $this->getUsedTime() < $secondsLimit;
  }

  public function getUsedMemory(): int {
    return $this->data["memory"];
  }

  /**
   * Compares the stats to the memory limit.
   * @param  int     $bytesLimit Limiting amout of bytes
   * @return boolean
   */
  public function isMemoryOK(int $bytesLimit): bool {
    return $this->getUsedMemory() < $bytesLimit;
  }

  public function getExitCode(): int {
    return $this->data["exitcode"];
  }

  public function getMessage(): string {
    return $this->data["message"];
  }

  public function __toString() {
    return Json::encode($this->data);
  }

}
