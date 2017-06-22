<?php

namespace App\Helpers\EvaluationResults;

/**
 * Interface for test results. Test is logical group of tasks marked with
 * the same test ID.
 */
interface ITestResult {
  const SCORE_MIN = 0.0;
  const SCORE_MAX = 1.0;

  /**
   * Get the ID of the test as it was defined in the config
   * @return string The ID
   */
  public function getId(): string;

  /**
   * Get the status of the whole test.
   * @return string The status, implementation specific
   */
  public function getStatus(): string;

  /**
   * Get parsed result statistics for each task
   * @return array List of results for each task in this test
   */
  public function getStats(): array;

  /**
   * Calculates the score for this test.
   * @return float The score between SCORE_MIN a SCORE_MAX
   */
  public function getScore(): float;

  /**
   * Gets array of execution tasks results
   * @return array List of results for all execution tasks in this test
   */
  public function getExecutionResults(): array;

  /**
   * Checks the configuration agains the actual performace.
   * @return boolean The result
   */
  public function didExecutionMeetLimits(): bool;

  /**
   * Checks if the execution time of all tasks meets the limit
   * @return boolean The result
   */
  public function isTimeOK(): bool;

  /**
   * Checks if the execution memory of all tasks meets the limit
   * @return boolean The result
   */
  public function isMemoryOK(): bool;

  /**
   * Get the return code
   * @return int If all tasks are successful, return 0. If not, return first nonzero code returned.
   */
  public function getExitCode(): int;

  /**
   * Get maximum used memory ratio of all tasks.
   * @return float The value in [0.0, 1.0]
   */
  public function getUsedMemoryRatio(): float;

  /**
   * Get maximum used time ratio of all tasks.
   * @return float The value in [0.0, 1.0]
   */
  public function getUsedTimeRatio(): float;

  /**
   * Get first nonempty message, if any exists or empty string.
   * @return string The message
   */
  public function getMessage(): string;

  /**
   * Get judge output.
   * @return string Standard output of judge binary (evaluation task)
   */
  public function getJudgeOutput(): string;
}
