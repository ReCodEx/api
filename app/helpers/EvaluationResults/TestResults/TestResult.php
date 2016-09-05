<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\TestConfig;

class TestResult implements ITestResult {

  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";
  const STATUS_SKIPPED = "SKIPPED";

  /** @var Test Test configuration */
  private $config;

  /** @var ExecutionTestResult Result of the execution task */
  private $executionResult;

  /** @var EvalutationTestResult Result of the evaluation task */
  private $evaluationResult;

  /** @var string Status of the test */
  private $status;

  /** @var Stats Stats of the execution task */
  private $stats;

  /** @var Limits Limits of the execution of this test */
  private $limits;

  private $statsInterpretation;

  public function __construct(
    TestConfig $config,
    ExecutionTaskResult $executionResult,
    EvaluationTaskResult $evaluationResult,
    string     $hardwareGroupId
  ) {
    $this->config = $config;
    $this->executionResult = $executionResult;
    $this->evaluationResult = $evaluationResult;
    $this->status = self::calculateStatus($executionResult->getStatus(), $evaluationResult->getStatus());
    $this->stats = $executionResult->getStats();
    $this->limits = $config->getLimits($hardwareGroupId);
    $this->statsInterpretation = new StatsInterpretation($this->stats, $this->limits);
  }


  /**
   * Determines the status of the test based on the previously reduced status of the test and the status of the next processed task result status.
   * @param   string $prevStatus    Current status
   * @param   string $resultStatus  Next status
   * @return  string Status of the reduced test tasks" statuses
   */
  public static function calculateStatus(string $prevStatus, string $resultStatus): string {
    if ($prevStatus === self::STATUS_OK) {
      return $resultStatus; // === "OK" | "SKIPPED" | "FAILED"
    } else if ($resultStatus === self::STATUS_OK) {
      return $prevStatus; // === "OK" | "SKIPPED" | "FAILED"
    } else if ($prevStatus === self::STATUS_SKIPPED) {
      return $resultStatus; // === "SKIPPED" | "FAILED"
    } else if ($resultStatus === self::STATUS_SKIPPED) {
      return $prevStatus; // === "SKIPPED" | "FAILED"
    } else {
      return self::STATUS_FAILED;
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
    return $this->executionResult->getStats();
  }

  /**
   * Calculates the score for this test.
   * @return float
   */
  public function getScore(): float {
    if ($this->didExecutionMeetLimits() === FALSE) {
      return 0;
    }

    return $this->evaluationResult->getScore();
  }

  /**
   * Checks the configuration agains the actual performace.
   * @return boolean
   */
  public function didExecutionMeetLimits(): bool {
    return $this->statsInterpretation->doesMeetAllCriteria();
  }

  public function getStatsInterpretation() {
    return $this->statsInterpretation;
  }

  /**
   * Checks if the execution time meets the limit
   * @return boolean
   */
  public function isTimeOK(): bool {
    return $this->statsInterpretation->isTimeOK();
  }

  /**
   * Checks if the allocated time meets the limit
   * @return boolean
   */
  public function isMemoryOK(): bool {
    return $this->statsInterpretation->isMemoryOK();
  }

  public function getExitCode(): int {
    return $this->stats->getExitCode();
  }

  public function getUsedMemoryRatio(): float {
    return $this->statsInterpretation->getUsedMemoryRatio();
  }

  public function getUsedTimeRatio(): float {
    return $this->statsInterpretation->getUsedTimeRatio();
  }

  public function getMessage(): string {
    return $this->stats->getMessage();
  }

  public function getJudgeOutput() {
    return $this->evaluationResult->getJudgeOutput();
  }

}
