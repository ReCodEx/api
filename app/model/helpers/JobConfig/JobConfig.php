<?php

namespace App\Model\Helpers\JobConfig;

use Symfony\Component\Yaml\Yaml;

class JobConfig {

  private $data;
  private $tasksCount;
  private $tasks = NULL;
  private $tests = NULL;

  /**
   * Job config data structure
   * @param array $data The deserialized data from the config file
   */
  public function __construct(string $jobId, array $data) {
    $this->data = $data;
    $this->data["submission"]["job-id"] = $jobId;
    $this->tasksCount = count($data["tasks"]);
  }

  /**
   * Get the identificator of this job
   * @return string
   */
  public function getJobId(): string {
    return $this->data["submission"]["job-id"];
  }

  /**
   * Returns the tasks of this configuration
   * @return Task[] The tasks
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
   * [getTests description]
   * @param  string $hardwareGroupId ID of a specific hardware group
   * @return Test[] [description]
   */
  public function getTests($hardwareGroupId) {
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

  /**
   * Serialize the 
   * @return string [description]
   */
  public function __toString() {
    return Yaml::dump($this->data);
  }

}
