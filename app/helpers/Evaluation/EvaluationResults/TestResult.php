<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\Limits;
use App\Helpers\JobConfig\TestConfig;

/**
 * Implementation of test results. In this case, each test can have tasks
 * of multiple types: zero or many initiation tasks, zero on many execution
 * tasks and exactly one task of evaluation type.
 */
class TestResult {

  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";
  const STATUS_SKIPPED = "SKIPPED";

  const SCORE_MIN = 0.0;
  const SCORE_MAX = 1.0;

  /** @var TestConfig Test configuration */
  private $config;

  /** @var TaskResult[] Result of the execution task */
  private $executionResults;

  /** @var TaskResult Result of the evaluation task */
  private $evaluationResult;

  /** @var string Status of the test */
  private $status;

  /** @var Limits[] Limits of the execution tasks of this test, indexed by task-id */
  private $limits;

  /** @var array Stats interpretation for each execution task (indexed by task-id)  */
  private $statsInterpretation;

  /**
   * Constructor
   * @param TestConfig            $config           Test configuration (contained tasks grupped by types, limits)
   * @param array                 $executionResults Results of execution tasks
   * @param TaskResult $evaluationResult Result of the one evaluation task
   * @param string                $hardwareGroupId  Identifier of hardware group on which was the test evaluated
   */
  public function __construct(
    TestConfig $config,
    array $executionResults,
    TaskResult $evaluationResult,
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

    // if the tested program exceeded its limits or scored zero points, we consider the test failed
    $isTestResultIncorrect = !$this->didExecutionMeetLimits() || $this->evaluationResult->getScore() === self::SCORE_MIN;

    if ($this->status === self::STATUS_OK && $isTestResultIncorrect) {
        $this->status = self::STATUS_FAILED;
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
   * @return string The ID
   */
  public function getId(): string {
    return $this->config->getId();
  }

  /**
   * Get the status of the whole test.
   * @return string The status, implementation specific
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Get parsed result statistics for each task
   * @return array List of results for each task in this test
   */
  public function getStats(): array {
    return array_map(
      function (TaskResult $result) {return $result->getStats(); },
      $this->executionResults
    );
  }

  /**
   * Gets array of execution tasks results
   * @return array List of results for all execution tasks in this test
   */
  public function getExecutionResults(): array {
    return $this->executionResults;
  }

  /**
   * Calculates the score for this test.
   * @return float The score between SCORE_MIN a SCORE_MAX
   */
  public function getScore(): float {
    if ($this->didExecutionMeetLimits() === FALSE || $this->getStatus() !== self::STATUS_OK) {
      // even though the judge might say different, this test failed and the score is zero
      return self::SCORE_MIN;
    }

    return $this->evaluationResult->getScore();
  }

  /**
   * Checks the configuration agains the actual performance.
   * @return boolean The result
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
   * @return boolean The result
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
   * Checks if the execution memory of all tasks meets the limit
   * @return boolean The result
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
   * Get the return code
   * @return int If all tasks are successful, return 0. If not, return first nonzero code returned.
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
   * @return float The value in [0.0, 1.0]
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
   * Get maximum used time ratio of all tasks.
   * @return float The value in [0.0, 1.0]
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
   * @return string The message
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
   * @return string Standard output of judge binary (evaluation task)
   */
  public function getJudgeOutput(): string {
    return $this->evaluationResult->getOutput();
  }

}
