<?php

namespace App\Model\Helpers\EvaluationResults;

use App\Model\Helpers\JobConfig\JobConfig;

class EvaluationResults {

  /** @var array Raw data from the results */
  private $data;

  /** @var JobConfig The configuration of the job */
  private $config;

  public function __construct(array $data, JobConfig $config) {
    $this->config = $config;
    $this->data = [];
    foreach ($data as $task) {
      $data[$task["task-id"]] = new TaskResult($task);
    }
  }

  public function hasEvaluationFailed() {
    // @todo
    return TRUE;
  }

  public function wasInitialisationOK() {
    foreach ($this->config->getTasks() as $task) {
      if ($task->isInitiationTask()) {
        $taskResult = $this->getTaskResult($task->getId());
        if (!$taskResult->isOK()) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }


  public function getTestsResults($hardwareGroupId) {
    return array_map(
      function($test) {
        return new TestResult(
          $test,
          $this->getTaskResult($test->getExecutionTask()->getId()),
          $this->getTaskResult($test->getEvaluationTask()->getId()),
          $hardwareGroupId
        );
      },
      $this->config->getTests()
    );
  }

  private function getTaskResult($taskId) {
    if (!isset($this->data[$taskId])) {
      // @todo Throw an exception
      return NULL;
    }

    return $this->data[$taskId];
  }

}
