<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;
use App\Helpers\JobConfig\Tasks\InitiationTaskType;

class TestConfig {

  /** @var string ID of the test */
  private $id;

  /** @var array The tasks which define this test */
  private $tasks;

  /** @var ExecutionTaskType The task which defines the execution part of the test */
  private $executionTaskType;

  /** @var EvaluationTaskType The task which defines the evaluation part of the test */
  private $evaluationTaskType;

  /** @var InitiationTaskType The task which defines the initiation part of the test */
  private $initiationTaskType;

  public function __construct(string $id, array $tasks) {
    $this->id = $id;
    $this->tasks = $tasks;

    // identify the important tasks
    foreach ($tasks as $task) {
      if ($task->isExecutionTask()) {
        $this->executionTaskType = new ExecutionTaskType($task);
      } else if ($task->isEvaluationTask()) {
        $this->evaluationTaskType = new EvaluationTaskType($task);
      } else if ($task->isInitiationTask()) {
        $this->initiationTaskType = new InitiationTaskType($task);
      }
    }

    if ($this->executionTaskType === NULL || $this->evaluationTaskType === NULL) {
      throw new JobConfigLoadingException("Each test must contain tasks of both types 'execution' and 'evaluation'. Test '{$id}' does not include at least one of them.");
    }
  }

  public function getId() {
    return $this->id;
  }

  public function getLimits($hardwareGroupId) {
    return $this->executionTaskType->getLimits($hardwareGroupId);
  }

  public function getExecutionTask() {
    return $this->executionTaskType->getTask();
  }

  public function getEvaluationTask() {
    return $this->evaluationTaskType->getTask();
  }

  public function getInitiationTask() {
    return $this->initiationTaskType->getTask();
  }

}
