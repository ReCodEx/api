<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\TestConfig;

/**
 * Test result common implementation for nonsuccessfull tests
 */
abstract class UnsuccessfulTestResult implements ITestResult {

  /** @var Test Test configuration */
  private $config;

  /** @var string Textual representation of test status */
  private $status;

  /**
   * Constructor
   * @param TestConfig $config Configuration of test
   * @param string     $status Aggregated evaluation status of whole test
   */
  public function __construct(TestConfig $config, string $status) {
    $this->config = $config;
    $this->status = $status;
  }

  /**
   * Get the ID of the test as it was defined in the config
   * @return string The ID
   */
  public function getId(): string {
    return $this->config->getId();
  }

  /**
   * Get the status of the whole test.
   * @return string Status string
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Get result statistics
   * @return array [] Unsuccessful test has no stats
   */
  public function getStats(): array {
    return [];
  }

  /**
   * Calculates the score for this test.
   * @return float 0.0 as minimum score value
   */
  public function getScore(): float {
    return 0;
  }

  /**
   * Checks the configuration agains the actual performace.
   * @return boolean FALSE
   */
  public function didExecutionMeetLimits(): bool {
    return FALSE;
  }

  /**
   * Get statistics interpretation
   * @return NULL Unsuccesful test has no interpretation
   */
  public function getStatsInterpretation() {
    return NULL;
  }

  /**
   * Checks if the execution time meets the limit
   * @return boolean FALSE
   */
  public function isTimeOK(): bool {
    return FALSE;
  }

  /**
   * Checks if the allocated time meets the limit
   * @return boolean FALSE
   */
  public function isMemoryOK(): bool {
    return FALSE;
  }

  /**
   * Get the return code
   * @return int 0
   */
  public function getExitCode(): int {
    return 0;
  }

  /**
   * Get maximum used memory ratio of all tasks.
   * @return float 0.0
   */
  public function getUsedMemoryRatio(): float {
    return 0.0;
  }

  /**
   * Get maximum used time ratio of all tasks.
   * @return float 0.0
   */
  public function getUsedTimeRatio(): float {
    return 0.0;
  }

  /**
   * Get first nonempty message, if any exists or empty string.
   * @return string Empty string
   */
  public function getMessage(): string {
    return "";
  }

  /**
   * Get judge output.
   * @return string NULL
   */
  public function getJudgeOutput(): string {
    return NULL;
  }

}
