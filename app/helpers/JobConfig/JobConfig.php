<?php

namespace App\Helpers\JobConfig;

use Symfony\Component\Yaml\Yaml;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\TaskFactory;
use App\Helpers\JobConfig\SubmissionHeader;


class JobConfig {
  const SUBMISSION_KEY = "submission";
  const TASKS_KEY = "tasks";

  /** @var array Additional top level data */
  private $data;

  /** @var SubmissionHeader */
  private $submissionHeader;

  /** @var array List of tasks */
  private $tasks = [];

  private $tests = NULL;

  /**
   * Job config data structure
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

  public function getId(): string {
    return $this->submissionHeader->getId();
  }

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
      $testId = $task->getTestId();
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

  public function cloneWithoutLimits(string $hwGroupId): JobConfig {
    $cfg = new JobConfig($this->toArray());
    $cfg->tasks = array_map(
      function ($originalTask) use ($hwGroupId) {
        $task = TaskFactory::create($originalTask->toArray());
        if ($task->isExecutionTask()) {
          $task->getSandboxConfig()->removeLimits($hwGroupId);
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
        $task = TaskFactory::create($originalTask->toArray());
        if ($task->isExecutionTask()) {
          if (!array_key_exists($task->getTestId(), $limits)) {
            return $task; // the limits for this task are unchanged
          }

          $sandboxConfig = $task->getSandboxConfig();
          $newTaskLimits = array_merge(
            $sandboxConfig->hasLimits($hwGroupId) ? $sandboxConfig->getLimits($hwGroupId)->toArray() : [],
            $limits[$task->getTestId()]
          );
          $task->setLimits($hwGroupId, new Limits($newTaskLimits));
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
      "tasks" => array_map(function($task) { return $task->toArray(); }, $this->tasks)
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
