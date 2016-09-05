<?php

namespace App\Helpers\EvaluationResults;

use App\Exception\SubmissionEvaluationFailedException;
use App\Helpers\JobConfig\JobConfig;

use Symfony\Component\Yaml\Yaml;

class EvaluationResults {

  /** @var array Raw data from the results */
  private $data;

  /** @var array Assoc array of the tasks */
  private $tasks = [];

  /** @var JobConfig The configuration of the job */
  private $config;

  public function __construct(array $data, JobConfig $config) {
    if ($data["job-id"] !== $config->getJobId()) {
      throw new ResultsLoadingException("Job ID of the configuration and the result do not match.");
    }

    if (!isset($data["results"])) {
      throw new ResultsLoadingException("Results are missing required field 'results'.");
    }

    if (!is_array($data["results"])) {
      throw new ResultsLoadingException("Results field of the results must be an array.");
    }

    $this->config = $config;
    $this->data = $data;
    $this->tasks = [];
    foreach ($data["results"] as $task) {
      $this->tasks[$task["task-id"]] = $task;
    }
  }

  public function wasInitialisationOK() {
    foreach ($this->config->getTasks() as $task) {
      if ($task->isInitiationTask()) {
        $taskResult = new TaskResult($this->tasks[$task->getId()]);
        if (!$taskResult->isOK()) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  public function getTestsResults($hardwareGroupId) {
    return array_map(
      function($test) use ($hardwareGroupId) {
        $executionData = $this->tasks[$test->getExecutionTask()->getId()];
        $evaluationData = $this->tasks[$test->getEvaluationTask()->getId()];    

        $taskA = new TaskResult($executionData);
        $taskB = new TaskResult($evaluationData);
        $status = TestResult::calculateStatus($taskA->getStatus(), $taskB->getStatus());
        switch ($status) {
          case TestResult::STATUS_OK:
            return new TestResult(
              $test,
              new ExecutionTaskResult($executionData),
              new EvaluationTaskResult($evaluationData),
              $hardwareGroupId
            );
          
          case TestResult::STATUS_SKIPPED:
            return new SkippedTestResult($test);

          default:
            return new FailedTestResult($test);
        }
      },
      $this->config->getTests($hardwareGroupId)
    );
  }

  private function getTaskResult($taskId) {
    if (!isset($this->tasks[$taskId])) {
      throw new ResultsLoadingException("There is no result for task '$taskId' defined in the job config.");
    }

    return $this->tasks[$taskId];
  }

  public function __toString() {
    return Yaml::dump($this->data);
  }

}
