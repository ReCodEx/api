<?php

namespace App\Helpers\JobConfig;

use Symfony\Component\Yaml\Yaml;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\TaskFactory;
use App\Helpers\JobConfig\SubmissionHeader;


/**
 * Stores all possible information about some particular job.
 * Job datas are parsed from structured configuration given at construction.
 */
class JobConfig {
  /** Submission header key */
  const SUBMISSION_KEY = "submission";
  /** Tasks config key */
  const TASKS_KEY = "tasks";

  /** @var array Additional top level data */
  private $data;
  /** @var SubmissionHeader Holds data about this job submission */
  private $submissionHeader;
  /** @var array List of tasks */
  private $tasks = [];

  /**
   * Job config data structure creation
   * @param array $data The deserialized data from the config file
   */
  public function __construct(array $data) {
    if (!isset($data[self::SUBMISSION_KEY])) {
      throw new JobConfigLoadingException("Job config does not contain the required '" . self::SUBMISSION_KEY . "' field.");
    }
    $this->submissionHeader = new SubmissionHeader($data[self::SUBMISSION_KEY]);
    unset($data[self::SUBMISSION_KEY]);

    if (!isset($data[self::TASKS_KEY])) {
      throw new JobConfigLoadingException("Job config does not contain the required '" . self::TASKS_KEY . "' field.");
    }
    foreach ($data[self::TASKS_KEY] as $taskConfig) {
      $this->tasks[] = TaskFactory::create($taskConfig);
    }
    unset($data[self::TASKS_KEY]);

    $this->data = $data;
  }

  /**
   * Get only job identification without type.
   * @return string
   */
  public function getId(): string {
    return $this->submissionHeader->getId();
  }

  /**
   * Get type of this job.
   * @return string
   */
  public function getType(): string {
    return $this->submissionHeader->getType();
  }

  /**
   * Get the identificator of this job including the type
   * @return string
   */
  public function getJobId(): string {
    return $this->submissionHeader->getJobId();
  }

  /**
   * Set the identificator of this job
   * @param string $jobId
   */
  public function setJobId(string $type, string $jobId) {
    $this->submissionHeader->setJobId($type, $jobId);
  }

  /**
   * Get URL of fileserver which will serve as source of files for worker
   * @return string
   */
  public function getFileCollector(): string {
    return $this->submissionHeader->getFileCollector();
  }

  /**
   * Sets URL of fileserver
   * @param string $fileCollector
   */
  public function setFileCollector(string $fileCollector) {
    $this->submissionHeader->setFileCollector($fileCollector);
  }

  /**
   * Returns the tasks of this configuration
   * @return TaskBase[] The tasks with instances of InternalTask and ExternalTask
   */
  public function getTasks(): array {
    return $this->tasks;
  }

  /**
   * Get the logical tests defined in the job config
   * @return TestConfig[] tests with their corresponding tasks
   */
  public function getTests() {
    $tasksByTests = [];
    foreach ($this->tasks as $task) {
      $id = $task->getTestId();
      if ($id !== NULL) {
        if (!isset($tasksByTests[$id])) {
          $tasksByTests[$id] = [];
        }

        $tasksByTests[$id][] = $task;
      }
    }

    return array_map(
      function ($tasks) {
        return new TestConfig(
          $tasks[0]->getTestId(), // there is always at least one task
          $tasks
        );
      },
      $tasksByTests
    );
  }

  /**
   * Counts the tasks defined in the job config
   * @return int The number of tasks defined in this job config
   */
  public function getTasksCount(): int {
    return count($this->tasks);
  }

  /**
   * Removes limits for all execution tasks (only specified hwgroup).
   * @param string $hwGroupId Hardware group identification
   */
  public function removeLimits(string $hwGroupId) {
    foreach ($this->tasks as $task) {
      if ($task->isExecutionTask()) {
        $task->getSandboxConfig()->removeLimits($hwGroupId);
      }
    }
  }

  /**
   * Set limits for execution tasks. For given hwgroup set limits of some/all execution tasks.
   * @param string $hwGroupId Hardware group for new limits
   * @param array $limits Map of task-id (key) and limits as array (value)
   */
  public function setLimits(string $hwGroupId, array $limits) {
    foreach ($this->tasks as $task) {
      if ($task->isExecutionTask()) {
        if (!array_key_exists($task->getId(), $limits)) {
          continue; // the limits for this task are unchanged
        } else {
          $sandboxConfig = $task->getSandboxConfig();
          $newTaskLimits = array_merge(
            $sandboxConfig->hasLimits($hwGroupId) ? $sandboxConfig->getLimits($hwGroupId)->toArray() : [],
            $limits[$task->getId()]
          );
          $task->getSandboxConfig()->setLimits(new Limits($newTaskLimits)); // $hwGroupId is inherited from current limits
        }
      }
    }
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::SUBMISSION_KEY] = $this->submissionHeader->toArray();
    $data[self::TASKS_KEY] = array_map(
      function($task) {
        return $task->toArray();
      }, $this->tasks
    );
    return $data;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

}
