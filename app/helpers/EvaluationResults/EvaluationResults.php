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
      throw new SubmissionEvaluationFailedException("JobID of the configuration and the result do not match.");
    }

    $this->config = $config;
    $this->data = $data;
    $this->tasks = [];
    foreach ($data["results"] as $task) {
      $this->tasks[$task["task-id"]] = $task;
    }
  }

  public function hasEvaluationFailed() {
    // @todo
    return FALSE;
  }

  public function wasInitialisationOK() {
    // initialisation could not have succeeded when the whole evaluation failed somehow
    if ($this->hasEvaluationFailed()) {
      return FALSE;
    }

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
        $executionTaskResult = new ExecutionTaskResult($this->tasks[$test->getExecutionTask()->getId()]);
        $evaluationTaskResult = new TaskResult($this->tasks[$test->getEvaluationTask()->getId()]);
        return new TestResult($test, $executionTaskResult, $evaluationTaskResult, $hardwareGroupId);
      },
      $this->config->getTests($hardwareGroupId)
    );
  }

  private function getTaskResult($taskId) {
    if (!isset($this->tasks[$taskId])) {
      // @todo throw an exception
      return NULL;
    }

    return $this->tasks[$taskId];
  }

  public function __toString() {
    return Yaml::dump($this->data);
  }

}
