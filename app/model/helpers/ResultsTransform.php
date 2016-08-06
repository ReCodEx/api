<?php

namespace App\Model\Helpers;

use App\Model\Entity\SubmissionEvaluation;

/**
 * @author  Šimon Rozsíval <simon@rozsival.com>
 */
class ResultsTransform {
    
  const TYPE_EXECUTION = "execution";
  const TYPE_EVALUATION = "evaluation";

  /**
   * Transforms the low-level information returned from the backend to a more reasonable data structure.
   * @param  array  $jobConfig Parsed YAML job config - valid
   * @param  array  $results   Parsed YAML results - valid
   * @return array             Transformed results
   */
  public static function transformLowLevelInformation(array $jobConfig, array $results): array {
    $simplifiedConfig = self::simplifyConfig($jobConfig);
    $results = self::createAssocResults($results["results"]);

    return array_reduce($simplifiedConfig, function ($carry, $task) use ($results) {
      $testId = $task["test-id"];
      $result = $results[$task["task-id"]];
      $test = isset($carry[$testId])
        ? $carry[$testId]
        : [ "status" => "OK" ];

      // update status of the test
      $test["status"] = self::extractStatus($test["status"], $result["status"]);

      // update the additional infromation of the test
      switch ($task["type"]) {
        case self::TYPE_EXECUTION:
          $test["stats"] = $result["sandbox_results"];
          break;
        case self::TYPE_EVALUATION:
          $test["score"] = self::extractScore($result);
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
   * @return  string Status of the reduced test tasks' statuses
   */
  public static function extractStatus(string $prevStatus, string $resultStatus): string {
    if ($prevStatus === "OK") {
      return $resultStatus; // === 'OK' | 'SKIPPED' | 'FAILED'
    } else if ($resultStatus === "OK") {
      return $prevStatus; // === 'OK' | 'SKIPPED' | 'FAILED'
    } else if ($prevStatus === "SKIPPED") {
      return $resultStatus; // === 'SKIPPED' | 'FAILED'
    } else if ($resultStatus === "SKIPPED") {
      return $prevStatus; // === 'SKIPPED' | 'FAILED'
    } else {
      return "FAILED";
    }
  }

  /**
   * Extract
   * @param  [type] $result [description]
   * @return [type]         [description]
   */
  public static function extractScore($result) {
    return isset($result["score"])
            ? intval($result["score"])
            : ($result["status"] === "OK" ? SubmissionEvaluation::MAX_SCORE : 0);
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
        if (isset($task["test-id"])) {
          return [
            "test-id" => $task["test-id"],
            "task-id" => $task["task-id"],
            "type"    => $task["type"]
          ];
        }

        // this task is not related to any test
        return NULL;
      },
      $jobConfig["tasks"]
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
      $result[$task["task-id"]] = $task;
      return $result;
    }, []);
  }


}
