<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\TestConfig;

abstract class UnsuccessfulTestResult implements ITestResult {

  /** @var Test Test configuration */
  private $config;

  /** @var string */
  private $status;

  public function __construct(TestConfig $config, string $status) {
    $this->config = $config;
    $this->status = $status;
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
    return NULL;
  }

  /**
   * Calculates the score for this test.
   * @return float
   */
  public function getScore(): float {
    return 0;
  }

  /**
   * Checks the configuration agains the actual performace.
   * @return boolean
   */
  public function didExecutionMeetLimits(): bool {
    return FALSE;
  }

  public function getStatsInterpretation() {
    return NULL;
  }

  /**
   * Checks if the execution time meets the limit
   * @return boolean
   */
  public function isTimeOK(): bool {
    return FALSE;
  }

  /**
   * Checks if the allocated time meets the limit
   * @return boolean
   */
  public function isMemoryOK(): bool {
    return FALSE;
  }

  public function getExitCode(): int {
    return 0;
  }

  public function getUsedMemoryRatio(): float {
    return 0.0;
  }

  public function getUsedTimeRatio(): float {
    return 0.0;
  }

  public function getMessage(): string {
    return "";
  }

  public function getJudgeOutput() {
    return NULL;
  }

}
