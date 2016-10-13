<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;
use App\Helpers\JobConfig\Tasks\InitiationTaskType;
use App\Helpers\JobConfig\Tasks\TaskBase;

class TestConfig {

  /** @var string ID of the test */
  private $id;

  /** @var array The tasks which define this test */
  private $tasks;

  /** @var array List of tasks which defines the initiation part of the test */
  private $initiationTaskType = [];

  /** @var array List of tasks which defines the execution part of the test */
  private $executionTaskType = [];

  /** @var EvaluationTaskType The task which defines the evaluation part of the test */
  private $evaluationTaskType;

  public function __construct(string $id, array $tasks) {
    $this->id = $id;
    $this->tasks = $tasks;

    // identify the important tasks
    foreach ($tasks as $task) {
      if ($task->isInitiationTask()) {
        $this->initiationTaskType[] = new InitiationTaskType($task);
      } else if ($task->isExecutionTask()) {
        $this->executionTaskType[] = new ExecutionTaskType($task);
      } else if ($task->isEvaluationTask()) {
        $this->evaluationTaskType = new EvaluationTaskType($task);
      }
    }

    if (empty($this->executionTaskType) || $this->evaluationTaskType === NULL) {
      throw new JobConfigLoadingException("Each test must contain at least on task of type 'execution' and exactly one of type 'evaluation'. Test '{$id}' does not meet these criteria.");
    }
  }

  public function getId() {
    return $this->id;
  }

  /**
   * Get limits for all included execution tasks.
   * @param string Desired hwgroup
   * @return array Map with task-id (key) and limits for this task (Limits type)
   */
  public function getLimits($hardwareGroupId): array {
    $limits = [];
    foreach ($this->executionTaskType as $task) {
      $limits[$task->getTask()->getId()] = $task->getLimits($hardwareGroupId);
    }
    return $limits;
  }

  /**
   * Get array of initiation tasks (TaskBase).
   */
  public function getInitiationTasks(): array {
    $tasks = [];
    foreach ($this->initiationTaskType as $task) {
      $tasks[] = $task->getTask();
    }
    return $tasks;
  }

  /**
   * Get array of execution tasks (TaskBase).
   */
  public function getExecutionTasks(): array {
    $tasks = [];
    foreach ($this->executionTaskType as $task) {
      $tasks[] = $task->getTask();
    }
    return $tasks;
  }

  /**
   * Get the only one evaluation task.
   */
  public function getEvaluationTask(): TaskBase {
    return $this->evaluationTaskType->getTask();
  }
}
