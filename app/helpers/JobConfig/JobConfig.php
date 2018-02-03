<?php

namespace App\Helpers\JobConfig;

use Symfony\Component\Yaml\Yaml;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\JobConfig\SubmissionHeader;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Stores all possible information about some particular job.
 * Job datas are parsed from structured configuration given at construction.
 */
class JobConfig {
  /** Submission header key */
  const SUBMISSION_KEY = "submission";
  /** Tasks config key */
  const TASKS_KEY = "tasks";

  /** @var bool Serialize so that humans are happy with the YAML code */
  private $humanReadable;
  /** @var array Additional top level data */
  private $data = [];
  /** @var SubmissionHeader Holds data about this job submission */
  private $submissionHeader;
  /** @var array List of tasks */
  private $tasks = [];

  /**
   * Construct basic instance of JobConfig.
   */
  public function __construct($humanReadable = FALSE) {
    $this->submissionHeader = new SubmissionHeader;
    $this->humanReadable = $humanReadable;
  }

  /**
   * Get SubmissionHeader structure which belongs to this configuration.
   * @return SubmissionHeader
   */
  public function getSubmissionHeader(): SubmissionHeader {
    return $this->submissionHeader;
  }

  /**
   * Set SubmissionHeader structure which belongs to this configuration.
   * @param SubmissionHeader $header
   * @return $this
   */
  public function setSubmissionHeader(SubmissionHeader $header) {
    $this->submissionHeader = $header;
    return $this;
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
   * @return $this
   */
  public function setJobId(string $jobId) {
    $this->submissionHeader->setJobId($jobId);
    return $this;
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
   * @return $this
   */
  public function setFileCollector(string $fileCollector) {
    $this->submissionHeader->setFileCollector($fileCollector);
    return $this;
  }

  /**
   * Get list of available hardware groups in this job configuration
   * @return array
   */
  public function getHardwareGroups(): array {
    return $this->submissionHeader->getHardwareGroups();
  }

  /**
   * Returns the tasks of this configuration
   * @return Task[] Structures with metadata describing job tasks
   */
  public function getTasks(): array {
    return $this->tasks;
  }

  /**
   * Add task to this job configuration.
   * @param Task $task
   * @return $this
   */
  public function addTask(Task $task) {
    $this->tasks[] = $task;
    return $this;
  }

  /**
   * Get the logical tests defined in the job config
   * @return TestConfig[] tests with their corresponding tasks
   */
  public function getTests(): array {
    $tasksByTests = [];
    foreach ($this->tasks as $task) {
      $id = $task->getTestId();
      if ($id !== null) {
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
   * Get array of limits which is indexed by test-id and then by task-id.
   * @return array map of limits
   */
  public function getLimits(): array {
    // Array of test-id as a key and the value is another array of task-id and limits as Limits type
    return array_map(
      function ($hardwareGroup) {
        return [
          "hardwareGroup" => $hardwareGroup,
          "tests" => array_map(
            function ($test) use ($hardwareGroup) {
              return $test->getLimits($hardwareGroup);
            },
            $this->getTests()
          )
        ];
      },
      $this->getHardwareGroups()
    );
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
    // Don't remove the hardware group from headers, because removeLimits() from
    // above just sets limits for that particular hwgroup to unlimited, so technically
    // the limits are still there.
    // $this->submissionHeader->removeHardwareGroup($hwGroupId);
  }

  /**
   * Set limits for execution tasks. For given hwgroup set limits of some/all execution tasks.
   * @param string $hwGroupId Hardware group for new limits
   * @param array $limits Map of task-id (key) and limits as array (value)
   * @return $this
   * @throws ForbiddenRequestException
   */
  public function setLimits(string $hwGroupId, array $limits) {
    foreach ($this->tasks as $task) {
      if ($task->isExecutionTask()) {
        if (!array_key_exists($task->getId(), $limits)) {
          continue; // the limits for this task are unchanged
        } else {
          $sandboxConfig = $task->getSandboxConfig();
          if (!$sandboxConfig->hasLimits($hwGroupId)) {
            throw new ForbiddenRequestException("It's not allowed to set limits for new hwgroup.");
          }

          // TODO: should this really be merge?
          $newTaskLimits = array_merge(
            $sandboxConfig->getLimits($hwGroupId)->toArray(),
            $limits[$task->getId()]
          );
          $sandboxConfig->setLimits((new Loader)->loadLimits($newTaskLimits)); // $hwGroupId is inherited from current limits
        }
      }
    }
    $this->submissionHeader->addHardwareGroup($hwGroupId);
    return $this;
  }

  /**
   * Get additional data.
   * Needed for forward compatibility.
   * @return array
   */
  public function getAdditionalData(): array {
    return $this->data;
  }

  /**
   * Set additional data, which cannot be parsed into structure.
   * Needed for forward compatibility.
   * @param array $data
   * @return $this
   */
  public function setAdditionalData(array $data) {
    $this->data = $data;
    return $this;
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
  public function __toString() {
    return Yaml::dump($this->toArray(), $this->humanReadable === TRUE ? 100 : -1); // do not inline yaml - 100 nesting limit ~ "infinity"
  }

}
