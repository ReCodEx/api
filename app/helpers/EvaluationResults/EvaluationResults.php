<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\TestConfig;
use App\Helpers\JobConfig\JobId;

use Symfony\Component\Yaml\Yaml;

class EvaluationResults {

  /** @var array Raw data from the results */
  private $data;

  /** @var array Assoc array of the tasks */
  private $tasks = [];

  /** @var JobConfig The configuration of the job */
  private $config;

  /** @var bool */
  private $initOK = TRUE;

  public function __construct(array $data, JobConfig $config) {
    if (!isset($data["job-id"])) {
      throw new ResultsLoadingException("Job ID is not set in the result.");
    }

    $jobId = new JobId($data["job-id"]);
    if ((string) $jobId !== $config->getJobId()) {
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

    // store all the reported results
    $this->tasks = [];
    foreach ($data["results"] as $task) {
      if (!isset($task["task-id"])) {
        throw new ResultsLoadingException("One of the task's result is missing 'task-id'");
      }

      $taskId = $task["task-id"];
      $this->tasks[$taskId] = new TaskResult($task);
    }

    // test if all the tasks in the config file have corresponding results
    // and also check if all the initiation tasks were successful
    // - missing task results are replaced with skipped task results
    foreach ($this->config->getTasks() as $taskCfg) {
      $id = $taskCfg->getId();
      if (!isset($this->tasks[$id])) {
        $this->tasks[$id] = new SkippedTaskResult($id);
      }

      if ($taskCfg->isInitiationTask() && !$this->tasks[$id]->isOK()) {
        $this->initOK = FALSE;
      }
    }
  }

  /**
   * @return bool Initialisation was OK
   */
  public function initOK() {
    return $this->initOK;
  }

  /**
   * Get
   * @param string $hardwareGroupId Hardware group
   * @return TestResult[]
   */
  public function getTestsResults($hardwareGroupId) {
    return array_map(function($test) use ($hardwareGroupId) {
      return $this->getTestResult($test, $hardwareGroupId);
    }, $this->config->getTests());
  }

  /**
   * @param TestConfig  $test       Configuration of the test
   * @param string $hardwareGroupId Hardware group
   * @return TestResult
   */
  public function getTestResult(TestConfig $test, $hardwareGroupId) {
    if ($this->initOK === FALSE) {
      return new SkippedTestResult($test);
    }

    $exec = $this->getExecutionTaskResult($test);
    $eval = $this->getEvaluationTaskResult($test);

    if ($exec->isSkipped()) {
      return new SkippedTestResult($test);
    }

    if ($exec->hasFailed() || $eval->isSkipped()) {
      return new FailedTestResult($test);
    }

    return new TestResult($test, $exec, $eval, $hardwareGroupId);
  }

  /**
   * @param TestConfig $test Configuration of the examined test
   * @return EvaluationTaskResult
   */
  private function getEvaluationTaskResult(TestConfig $test) {
    $id = $test->getEvaluationTask()->getId();
    return $this->tasks[$id]->getAsEvaluationTaskResult();
  }

  /**
   * @param TestConfig $test Configuration of the examined test
   * @return ExecutionTaskResult
   */
  private function getExecutionTaskResult(TestConfig $test) {
    $id = $test->getExecutionTask()->getId();
    return $this->tasks[$id]->getAsExecutionTaskResult();
  }

  public function __toString() {
    return Yaml::dump($this->data);
  }

}
