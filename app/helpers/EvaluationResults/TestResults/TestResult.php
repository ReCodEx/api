<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\TestConfig;

class TestResult implements ITestResult {

  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";
  const STATUS_SKIPPED = "SKIPPED";

  const SCORE_MIN = 0.0;
  const SCORE_MAX = 1.0;

  /** @var TestConfig Test configuration */
  private $config;

  /** @var ExecutionTestResult[] Result of the execution task */
  private $executionResults;

  /** @var EvalutationTestResult Result of the evaluation task */
  private $evaluationResult;

  /** @var string Status of the test */
  private $status;

  /** @var Limits[] Limits of the execution tasks of this test, indexed by task-id */
  private $limits;

  /** @var array Stats interpretation for each execution task (indexed by task-id)  */
  private $statsInterpretation;

  public function __construct(
    TestConfig $config,
    array $executionResults,
    EvaluationTaskResult $evaluationResult,
    string     $hardwareGroupId
  ) {
    $this->config = $config;
    $this->executionResults = $executionResults;
    $this->evaluationResult = $evaluationResult;
    $this->limits = $config->getLimits($hardwareGroupId);
    foreach ($this->executionResults as $execRes) {
      $stats = $execRes->getStats();
      $limit = $this->limits[$execRes->getId()];
      $this->statsInterpretation[] = new StatsInterpretation($stats, $limit);
    }

    // set the status based on the tasks runtime and their results
    $this->status = self::STATUS_OK;
    foreach ($this->executionResults as $result) { $this->status = self::calculateStatus($this->status, $result->getStatus()); }
    $this->status = self::calculateStatus($this->status, $evaluationResult->getStatus());
    
    if ($this->status === self::STATUS_OK &&
      (!$this->didExecutionMeetLimits() || !$this->evaluationResult->getScore() === self::SCORE_MIN)) {
        $this->status = self::STATUS_FAILED; // the tasks execution was OK, but the result is not OK
    }
  }


  /**
   * Determines the status of the test based on the previously reduced status of the test and the status of the next processed task result status.
   * @param   string $curStatus      Current status
   * @param   string $newTaskStatus  Next status
   * @return  string Status of the reduced test tasks statuses
   */
  public static function calculateStatus(string $curStatus, string $newTaskStatus): string {
    if ($curStatus === self::STATUS_OK) {
      return $newTaskStatus;
    } else {
      return $curStatus;
    }
  }

  /**
   * Get the ID of the test as it was defined in the config
   * @return string
   */
  public function getId(): string {
    return $this->config->getId();
  }

  /**
   * Get the status of the whole test.
   * @return string
   */
  public function getStatus(): string {
    return $this->status;
  }

  public function getStats() {
    return array_map(
      function ($result) {return $result->getStats(); },
      $this->executionResults
    );
  }

  /**
   * Calculates the score for this test.
   * @return float
   */
  public function getScore(): float {
    if ($this->didExecutionMeetLimits() === FALSE || $this->getStatus() !== self::STATUS_OK) {
      // even though the judge might say different, this test failed and the score is zero
      return self::SCORE_MIN;
    }

    return $this->evaluationResult->getScore();
  }

  /**
   * Checks the configuration agains the actual performace.
   * @return boolean
   */
  public function didExecutionMeetLimits(): bool {
    foreach ($this->statsInterpretation as $interpretation) {
      if ($interpretation->doesMeetAllCriteria() === FALSE) {
        return FALSE;
      }
    }
    return TRUE;
  }

  public function getStatsInterpretation(): array {
    return $this->statsInterpretation;
  }

  /**
   * Checks if the execution time of all tasks meets the limit
   * @return boolean
   */
  public function isTimeOK(): bool {
    foreach ($this->statsInterpretation as $interpretation) {
      if ($interpretation->isTimeOK() === FALSE) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Checks if the allocated memory of all tasks meets the limit
   * @return boolean
   */
  public function isMemoryOK(): bool {
    foreach ($this->statsInterpretation as $interpretation) {
      if ($interpretation->isMemoryOK() === FALSE) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * If all tasks are successful, return 0. If not, return first nonzero code returned.
   */
  public function getExitCode(): int {
    foreach ($this->getStats() as $stat) {
      if ($stat->getExitCode() !== 0) {
        return $stat->getExitCode();
      }
    }
    return 0;
  }

  /**
   * Get maximum used memory ratio of all tasks.
   */
  public function getUsedMemoryRatio(): float {
    $maxRatio = 0.0;
    foreach ($this->statsInterpretation as $interpretation) {
      if ($interpretation->getUsedMemoryRatio() > $maxRatio) {
        $maxRatio = $interpretation->getUsedMemoryRatio();
      }
    }
    return $maxRatio;
  }

  /**
   * Used time ratio as sum of ratios of all tasks.
   */
  public function getUsedTimeRatio(): float {
    $sumRatio = 0.0;
    foreach ($this->statsInterpretation as $interpretation) {
      $sumRatio += $interpretation->getUsedTimeRatio();
    }
    return $sumRatio;
  }

  /**
   * Get first nonempty message, if any exists or empty string.
   */
  public function getMessage(): string {
    foreach ($this->getStats() as $stat) {
      if (!empty($stat->getMessage())) {
        return $stat->getMessage();
      }
    }
    return "";
  }

  /**
   * Get judge output.
   */
  public function getJudgeOutput() {
    return $this->evaluationResult->getJudgeOutput();
  }

}
