<?php

namespace App\Helpers\JobConfig;

use Symfony\Component\Yaml\Yaml;
use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\InvalidArgumentException;

class JobConfig {

  /** @var array */
  private $data;

  /** @var SubmissionHeader */
  private $submissionHeader;

  private $tasksCount;
  private $tasks = NULL;
  private $tests = NULL;

  /**
   * Job config data structure
   * @param array $data The deserialized data from the config file
   */
  public function __construct(array $data) {
    if (!isset($data["submission"])) {
      throw new JobConfigLoadingException("Job config does not contain required section 'submission'.");
    }

    if (!is_array($data["submission"])) {
      throw new JobConfigLoadingException("Job config section 'submission' must be an array.");
    }

    if (!isset($data["tasks"])) {
      throw new JobConfigLoadingException("Job config does not contain required section 'tasks'.");
    }

    if (!is_array($data["tasks"])) {
      throw new JobConfigLoadingException("Job config section 'tasks' must be an array.");
    }

    $this->data = $data;
    $this->submissionHeader = new SubmissionHeader($data["submission"]);
    $this->tasksCount = count($data["tasks"]);
  }

  /**
   * Get the identificator of this job
   * @return string
   */
  public function getJobId(): string {
    return $this->submissionHeader->getJobId();
  }

  /**
   * Get the identificator of this job
   * @param string $jobId
   */
  public function setJobId(string $jobId) {
    $this->submissionHeader->setJobId($jobId);
  }

  /**
   * Returns the tasks of this configuration
   * @return TaskConfig[] The tasks
   */
  public function getTasks(): array {
    if ($this->tasks === NULL) {
      $this->tasks = array_map(
        function ($task) {
          return new TaskConfig($task);
        },
        $this->data["tasks"]
      );
    }

    return $this->tasks;
  }

  /**
   * Get the logical tests defined in the job config
   * @return TestConfig[] tests with their corresponding tasks
   */
  public function getTests() {
    if ($this->tests === NULL) {
      $tasksByTests = [];
      foreach ($this->getTasks() as $task) {
        $id = $task->getTestId();
        if ($id !== NULL) {
          if (!isset($tasksByTests[$id])) {
            $tasksByTests[$id] = [];
          }

          $tasksByTests[$id][] = $task;
        }
      }

      $this->tests = array_map(
        function ($tasks) {
          return new TestConfig(
            $tasks[0]->getTestId(),
            $tasks
          );
        },
        $tasksByTests
      );
    }

    return $this->tests;
  }

  /**
   * Counts the tasks defined in the job config
   * @return int The number of tasks defined in this job config
   */
  public function getTasksCount(): int {
    return $this->tasksCount;
  }

  public function cloneWithoutLimits(string $hwGroupId): JobConfig {
    $cfg = new JobConfig($this->toArray());
    $cfg->tasks = array_map(
      function ($originalTask) use ($hwGroupId) {
        $task = new TaskConfig($originalTask->toArray());
        if ($task->isExecutionTask()) {
          $task = $task->getAsExecutionTask();
          $task->removeLimits($hwGroupId);
        }

        return $task;
      },
      $this->getTasks()
    );

    return $cfg;
  }

  /**
   *
   * @param string $hwGroupId
   * @param array $limits
   * @return JobConfig newly created instance of job configuration with given limits
   */
  public function cloneWithNewLimits(string $hwGroupId, array $limits): JobConfig {
    $cfg = new JobConfig($this->toArray());
    $cfg->tasks = array_map(
      function ($originalTask) use ($hwGroupId, $limits) {
        $task = new TaskConfig($originalTask->toArray());
        if ($task->isExecutionTask()) {
          if (!array_key_exists($task->getTestId(), $limits)) {
            return $task; // the limits for this task are unchanged
          }

          $task = $task->getAsExecutionTask();
          $taskLimits = array_merge(
            $task->hasLimits($hwGroupId) ? $task->getLimits($hwGroupId)->toArray() : [],
            $limits[$task->getTestId()]
          );
          $task->setLimits($hwGroupId, new Limits($taskLimits));
        }

        return $task;
      },
      $this->getTasks()
    );

    return $cfg;
  }

  public function toArray() {
    return [
      "submission" => $this->submissionHeader->toArray(),
      "tasks" => array_map(function($task) { return $task->toArray(); }, $this->getTasks())
    ];
  }

  /**
   * Serialize the config
   * @return string
   */
  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
