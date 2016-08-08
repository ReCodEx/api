<?php

namespace App\Model\Helpers;

use App\Model\Entity\SubmissionEvaluation;

/**
 * @author  Šimon Rozsíval <simon@rozsival.com>
 */
class ResultsTransform {
    
  const TYPE_EXECUTION = "execution";
  const TYPE_EVALUATION = "evaluation";

  const FIELD_JUDGE_OUTPUT = "judge_output";
  const FIELD_STATUS = "status";
  const FIELD_RESULTS = "results";
  const FIELD_TEST_ID = "test-id";
  const FIELD_TASK_ID = "task-id";
  const FIELD_TYPE = "type";
  const FIELD_STATS = "stats";
  const FIELD_TASKS = "tasks";
  const FIELD_SANDBOX = "sandbox";
  const FIELD_SANDBOX_RESULTS = "sandbox_results";
  const FIELD_LIMITS = "limits";
  const FIELD_SCORE = "score";
  const FIELD_HW_GROUP_ID = "hw-group-id";

  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";
  const STATUS_SKIPPED = "SKIPPED";

  /**
   * Transforms the low-level information returned from the backend to a more reasonable data structure.
   * @param  array  $jobConfig Parsed YAML job config - valid
   * @param  array  $results   Parsed YAML results - valid
   * @return array             Transformed results
   */
  public static function transformLowLevelInformation(array $jobConfig, array $results): array {
    $simplifiedConfig = self::simplifyConfig($jobConfig);
    $results = self::createAssocResults($results[self::FIELD_RESULTS]);

    return array_reduce($simplifiedConfig, function ($carry, $task) use ($results) {
      $testId = $task[self::FIELD_TEST_ID];
      $result = $results[$task[self::FIELD_TASK_ID]];
      $test = isset($carry[$testId])
        ? $carry[$testId]
        : [ self::FIELD_STATUS => self::STATUS_OK ];

      // update status of the test
      $test[self::FIELD_STATUS] = self::extractStatus($test[self::FIELD_STATUS], $result[self::FIELD_STATUS]);

      // update the additional infromation of the test
      switch ($task[self::FIELD_TYPE]) {
        case self::TYPE_EXECUTION:
          $test[self::FIELD_STATS] = $result[self::FIELD_SANDBOX_RESULTS];
          $test[self::FIELD_LIMITS] = $task[self::FIELD_LIMITS];
          break;
        case self::TYPE_EVALUATION:
          $test[self::FIELD_SCORE] = self::extractScore($result);
          break;
      }

      $carry[$testId] = $test;
      return $carry;
    }, []);
  }

  /**
   * Determines the status of the test based on the previously reduced status of the test and the status of the next processed task result status.
   * @param   string $prevStatus    Current status
   * @param   string $resultStatus  Next status
   * @return  string Status of the reduced test tasks" statuses
   */
  public static function extractStatus(string $prevStatus, string $resultStatus): string {
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
   * Extract
   * @param  [type] $result [description]
   * @return [type]         [description]
   */
  public static function extractScore($result) {
    return isset($result[self::FIELD_JUDGE_OUTPUT]) && !empty($result[self::FIELD_JUDGE_OUTPUT])
            ? min(1, max(0, floatval(strtok($result[self::FIELD_JUDGE_OUTPUT], " ")))) // @todo: Make sure this is correctly named
            : ($result[self::FIELD_STATUS] === self::STATUS_OK ? 1 : 0);
  }

  /**
   * Remove unnecessary fields of the task and remove all unnecessary tasks from the array
   * @param  array  $jobConfig [description]
   * @return array
   */
  public static function simplifyConfig(array $jobConfig): array {
    // preserve only the necessary fields in necessary tasks
    $tasks = array_map(
      function ($task) {
        if (isset($task[self::FIELD_TEST_ID])) {
          $importantData = [
            self::FIELD_TEST_ID => $task[self::FIELD_TEST_ID],
            self::FIELD_TASK_ID => $task[self::FIELD_TASK_ID],
            self::FIELD_TYPE    => $task[self::FIELD_TYPE]
          ];

          if ($importantData[self::FIELD_TYPE] === self::TYPE_EXECUTION) {
            if (!isset($task[self::FIELD_SANDBOX]) || !isset($task[self::FIELD_SANDBOX][self::FIELD_LIMITS])) {
              // @todo throw an exception
            }

            $importantData[self::FIELD_LIMITS] = [];
            foreach ($task[self::FIELD_SANDBOX][self::FIELD_LIMITS] as $hwGroupLimits) {
              $importantData[self::FIELD_LIMITS][$hwGroupLimits[self::FIELD_HW_GROUP_ID]] = $hwGroupLimits;
            }
          }

          return $importantData;
        }

        // this task is not related to any test
        return NULL;
      },
      $jobConfig[self::FIELD_TASKS]
    );

    // remove unnecessary tasks
    return array_values(array_filter(
      $tasks,
      function ($task) { return !is_null($task); }
    ));
  }

  /**
   * Transform the results to an associative array 
   * @param  array  $results  The array of tasks
   * @return array            Associative array - task ID is the key
   */
  public static function createAssocResults(array $results): array {
    return array_reduce($results, function ($result, $task) {
      $result[$task[self::FIELD_TASK_ID]] = $task;
      return $result;
    }, []);
  }


}
